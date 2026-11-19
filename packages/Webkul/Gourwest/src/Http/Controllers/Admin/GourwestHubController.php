<?php

namespace Webkul\Gourwest\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Gourwest\Models\CustomerProfile;
use Webkul\Gourwest\Models\Integration;
use Webkul\Gourwest\Models\ServiceTicket;
use Webkul\Gourwest\Models\SyncEvent;
use Webkul\Gourwest\Repositories\CustomerProfileRepository;
use Webkul\Gourwest\Repositories\IntegrationRepository;
use Webkul\Gourwest\Repositories\ServiceTicketRepository;
use Webkul\Gourwest\Support\GoldenRecordResolver;
use Webkul\Gourwest\Support\GourwestDemoSeeder;
use Webkul\Gourwest\Support\IntegrationSyncService;
use Webkul\Gourwest\Support\RegionCatalog;
use Webkul\Gourwest\Support\WarrantyBenchmarkRunner;
use Webkul\Security\Support\SecurityAuditRecorder;

class GourwestHubController extends Controller
{
    public function __construct(
        protected CustomerProfileRepository $profileRepository,
        protected IntegrationRepository $integrationRepository,
        protected ServiceTicketRepository $ticketRepository,
        protected GoldenRecordResolver $goldenRecordResolver,
        protected IntegrationSyncService $integrationSyncService,
        protected WarrantyBenchmarkRunner $benchmarkRunner,
        protected GourwestDemoSeeder $demoSeeder
    ) {}

    public function index(): View
    {
        return view('gourwest::admin.index');
    }

    public function overview(Request $request): JsonResponse
    {
        $authorizedUserIds = bouncer()->getAuthorizedUserIds();

        $profileQuery = $this->profileRepository
            ->scopedQuery($authorizedUserIds)
            ->orderByDesc('is_primary_golden')
            ->orderByDesc('loyalty_points')
            ->orderBy('id');

        $regionFilter = strtoupper(trim((string) $request->query('region', '')));

        if ($regionFilter !== '' && RegionCatalog::isSupported($regionFilter)) {
            $profileQuery->where('region_code', $regionFilter);
        }

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $profileQuery->where(function ($q) use ($escaped): void {
                $q->where('loyalty_member_number', 'like', "%{$escaped}%")
                    ->orWhere('golden_record_uid', 'like', "%{$escaped}%")
                    ->orWhere('home_outlet', 'like', "%{$escaped}%")
                    ->orWhereHas('person', function ($pq) use ($escaped): void {
                        $pq->where('name', 'like', "%{$escaped}%");
                    });
            });
        }

        /** @var Collection<int, CustomerProfile> $profiles */
        $profiles = $profileQuery->limit(200)->get();

        /** @var Collection<int, CustomerProfile> $allScopedPrimary */
        $allScopedPrimary = $this->profileRepository
            ->scopedQuery($authorizedUserIds)
            ->where('is_primary_golden', true)
            ->get();

        $duplicates = $this->goldenRecordResolver->detectDuplicates($authorizedUserIds);

        /** @var Collection<int, Integration> $integrations */
        $integrations = Integration::query()->orderBy('id')->get();

        /** @var Collection<int, SyncEvent> $recentEvents */
        $recentEvents = SyncEvent::query()
            ->with('integration')
            ->latest('id')
            ->limit(20)
            ->get();

        $ticketQuery = $this->ticketRepository
            ->scopedQuery($authorizedUserIds)
            ->orderByRaw("CASE WHEN status = 'resolved' THEN 1 ELSE 0 END ASC")
            ->orderByDesc('id');

        if ($regionFilter !== '' && RegionCatalog::isSupported($regionFilter)) {
            $ticketQuery->where('region_code', $regionFilter);
        }

        /** @var Collection<int, ServiceTicket> $tickets */
        $tickets = $ticketQuery->limit(100)->get();

        $allScopedTickets = $this->ticketRepository->scopedQuery($authorizedUserIds)->get();

        $regionsSummary = [];

        foreach (RegionCatalog::all() as $code => $meta) {
            $regionProfiles = $allScopedPrimary->where('region_code', $code);
            $regionOpenTickets = $allScopedTickets
                ->where('region_code', $code)
                ->where('status', '!=', 'resolved');

            $regionsSummary[] = [
                'code' => $code,
                'name' => $meta['name'],
                'currency' => $meta['currency'],
                'timezone' => $meta['timezone'],
                'privacy_regime' => $meta['privacy_regime'],
                'privacy_statute' => $meta['privacy_statute'],
                'hub_name' => $meta['hub_name'],
                'data_residency' => $meta['data_residency'],
                'cluster' => $meta['cluster'],
                'golden_customers' => $regionProfiles->count(),
                'total_points' => (int) $regionProfiles->sum('loyalty_points'),
                'lifetime_spend_aud' => round((float) $regionProfiles->sum(static fn (CustomerProfile $p): float => (float) $p->lifetime_spend_aud), 2),
                'open_tickets' => $regionOpenTickets->count(),
            ];
        }

        $benchmark = $this->benchmarkRunner->run();
        $openTicketsCount = $allScopedTickets->where('status', '!=', 'resolved')->count();
        $healthyIntegrationsCount = $integrations->where('status', 'healthy')->count();
        $deadLetterCount = (int) $integrations->sum('failed_events_count');

        return new JsonResponse([
            'data' => [
                'kpis' => [
                    'golden_customers' => $allScopedPrimary->count(),
                    'total_profiles' => $this->profileRepository->scopedQuery($authorizedUserIds)->count(),
                    'duplicate_clusters' => count($duplicates),
                    'total_loyalty_points' => (int) $allScopedPrimary->sum('loyalty_points'),
                    'total_lifetime_spend_aud' => round((float) $allScopedPrimary->sum(static fn (CustomerProfile $p): float => (float) $p->lifetime_spend_aud), 2),
                    'healthy_integrations' => $healthyIntegrationsCount,
                    'total_integrations' => $integrations->count(),
                    'dead_letter_events' => $deadLetterCount,
                    'open_sla_tickets' => $openTicketsCount,
                    'p95_latency_ms' => $benchmark['p95_ms'],
                    'passes_3s_sla' => $benchmark['passes_3s_sla'],
                ],
                'regions' => $regionsSummary,
                'customers' => $profiles->map(fn (CustomerProfile $p): array => $this->serializeProfile($p))->values(),
                'duplicate_candidates' => $duplicates,
                'integrations' => $integrations->map(fn (Integration $i): array => $this->serializeIntegration($i))->values(),
                'sync_events' => $recentEvents->map(fn (SyncEvent $e): array => $this->serializeSyncEvent($e))->values(),
                'tickets' => $tickets->map(fn (ServiceTicket $t): array => $this->serializeTicket($t))->values(),
                'benchmark' => $benchmark,
            ],
        ]);
    }

    public function seedDemo(): JsonResponse
    {
        if (bouncer()->getAuthorizedUserIds() !== null) {
            abort(401, trans('admin::app.errors.unauthorized'));
        }

        $summary = $this->demoSeeder->seed();

        return new JsonResponse([
            'data' => $summary,
            'message' => trans('gourwest::app.messages.seed-success'),
        ]);
    }

    public function deduplicate(): JsonResponse
    {
        $authorizedUserIds = bouncer()->getAuthorizedUserIds();
        $summary = $this->goldenRecordResolver->mergeDuplicates($authorizedUserIds);

        return new JsonResponse([
            'data' => $summary,
            'message' => trans('gourwest::app.messages.deduplicate-success', [
                'count' => $summary['profiles_merged'],
            ]),
        ]);
    }

    public function syncIntegration(Request $request, int $id): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $this->integrationRepository->findOrFail($id);

        $regionCode = is_string($request->input('region_code')) ? $request->input('region_code') : null;
        $result = $this->integrationSyncService->triggerSyncPulse($integration, $regionCode);

        return new JsonResponse([
            'data' => [
                'integration' => $this->serializeIntegration($result['integration']),
                'event' => $this->serializeSyncEvent($result['event']),
            ],
            'message' => trans('gourwest::app.messages.sync-success', [
                'system' => $result['integration']->name,
            ]),
        ]);
    }

    public function replayFailed(Request $request): JsonResponse
    {
        $integrationId = $request->filled('integration_id') ? (int) $request->input('integration_id') : null;
        $summary = $this->integrationSyncService->replayDeadLetters($integrationId);

        return new JsonResponse([
            'data' => $summary,
            'message' => trans('gourwest::app.messages.replay-success', [
                'count' => $summary['replayed_count'],
            ]),
        ]);
    }

    public function adjustLoyalty(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile_id' => ['required', 'integer', 'exists:gourwest_customer_profiles,id'],
            'points_delta' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        /** @var CustomerProfile $profile */
        $profile = $this->profileRepository->findOrFail((int) $validated['profile_id']);

        $this->preventUnauthorizedAccess($this->profileRepository->resolveOwnerIds($profile));

        $updated = DB::transaction(function () use ($profile, $validated): CustomerProfile {
            /** @var CustomerProfile $locked */
            $locked = CustomerProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            $previousPoints = (int) $locked->loyalty_points;
            $delta = (int) $validated['points_delta'];
            $nextPoints = max(0, $previousPoints + $delta);
            $nextTier = RegionCatalog::tierForPoints($nextPoints);

            $locked->update([
                'loyalty_points' => $nextPoints,
                'loyalty_tier' => $nextTier,
                'last_synced_at' => now(),
            ]);

            SecurityAuditRecorder::record(
                event: 'gourwest.loyalty.adjusted',
                auditableType: 'gourwest_customer_profile',
                auditableId: $locked->id,
                metadata: [
                    'loyalty_member_number' => $locked->loyalty_member_number,
                    'previous_points' => $previousPoints,
                    'points_delta' => $delta,
                    'new_points' => $nextPoints,
                    'new_tier' => $nextTier,
                    'reason' => (string) $validated['reason'],
                ]
            );

            return $locked->load(['person', 'owner']);
        });

        return new JsonResponse([
            'data' => $this->serializeProfile($updated),
            'message' => trans('gourwest::app.messages.loyalty-adjusted'),
        ]);
    }

    public function storeTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile_id' => ['nullable', 'integer', 'exists:gourwest_customer_profiles,id'],
            'region_code' => ['required', 'string', Rule::in(RegionCatalog::codes())],
            'channel' => ['required', 'string', Rule::in(['mobile_app', 'in_store_pos', 'web_portal', 'contact_centre', 'loyalty_desk'])],
            'category' => ['required', 'string', Rule::in(['duplicate_account', 'loyalty_discrepancy', 'delayed_response', 'order_sync', 'privacy_request'])],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'subject' => ['required', 'string', 'max:191'],
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $personId = null;

        if (! empty($validated['profile_id'])) {
            /** @var CustomerProfile $profile */
            $profile = $this->profileRepository->findOrFail((int) $validated['profile_id']);
            $this->preventUnauthorizedAccess($this->profileRepository->resolveOwnerIds($profile));
            $personId = $profile->person_id;
        }

        $priority = (string) $validated['priority'];
        $actorId = (int) auth()->guard('user')->id();

        /** @var ServiceTicket $ticket */
        $ticket = $this->ticketRepository->create([
            'ticket_number' => 'GW-SLA-'.strtoupper(Str::random(6)),
            'person_id' => $personId,
            'user_id' => $actorId > 0 ? $actorId : null,
            'region_code' => strtoupper((string) $validated['region_code']),
            'channel' => (string) $validated['channel'],
            'category' => (string) $validated['category'],
            'priority' => $priority,
            'status' => 'open',
            'subject' => (string) $validated['subject'],
            'summary' => isset($validated['summary']) ? (string) $validated['summary'] : null,
            'sla_due_at' => now()->addHours(RegionCatalog::slaHoursForPriority($priority)),
            'resolved_at' => null,
        ]);

        $ticket->load(['person', 'owner']);

        SecurityAuditRecorder::record(
            event: 'gourwest.ticket.created',
            auditableType: 'gourwest_service_ticket',
            auditableId: $ticket->id,
            metadata: [
                'ticket_number' => $ticket->ticket_number,
                'region_code' => $ticket->region_code,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
            ]
        );

        return new JsonResponse([
            'data' => $this->serializeTicket($ticket),
            'message' => trans('gourwest::app.messages.ticket-created'),
        ], 201);
    }

    public function resolveTicket(Request $request, int $id): JsonResponse
    {
        /** @var ServiceTicket $ticket */
        $ticket = $this->ticketRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($ticket->user_id);

        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $summary = $ticket->summary;

        if (! empty($validated['resolution_note'])) {
            $note = trim((string) $validated['resolution_note']);
            $summary = $summary !== null && $summary !== ''
                ? $summary."\n[Resolved]: ".$note
                : '[Resolved]: '.$note;
        }

        /** @var ServiceTicket $updated */
        $updated = $this->ticketRepository->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'summary' => $summary,
        ], $ticket->id);

        $updated->load(['person', 'owner']);

        SecurityAuditRecorder::record(
            event: 'gourwest.ticket.resolved',
            auditableType: 'gourwest_service_ticket',
            auditableId: $updated->id,
            metadata: [
                'ticket_number' => $updated->ticket_number,
                'region_code' => $updated->region_code,
            ]
        );

        return new JsonResponse([
            'data' => $this->serializeTicket($updated),
            'message' => trans('gourwest::app.messages.ticket-resolved'),
        ]);
    }

    public function benchmark(): JsonResponse
    {
        $report = $this->benchmarkRunner->run();

        SecurityAuditRecorder::record(
            event: 'gourwest.benchmark.executed',
            auditableType: 'gourwest_benchmark',
            auditableId: 'warranty',
            metadata: [
                'p50_ms' => $report['p50_ms'],
                'p95_ms' => $report['p95_ms'],
                'p99_ms' => $report['p99_ms'],
                'passes_3s_sla' => $report['passes_3s_sla'],
                'five_million_ready' => $report['five_million_ready'],
            ]
        );

        return new JsonResponse([
            'data' => $report,
            'message' => trans('gourwest::app.messages.benchmark-complete'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProfile(CustomerProfile $profile): array
    {
        $rawEmails = $profile->person?->getAttribute('emails');
        $rawPhones = $profile->person?->getAttribute('contact_numbers');
        $emails = is_array($rawEmails) ? $rawEmails : [];
        $phones = is_array($rawPhones) ? $rawPhones : [];
        $primaryEmail = isset($emails[0]) && is_array($emails[0]) && isset($emails[0]['value']) ? (string) $emails[0]['value'] : '';
        $primaryPhone = isset($phones[0]) && is_array($phones[0]) && isset($phones[0]['value']) ? (string) $phones[0]['value'] : '';
        $personName = $profile->person?->getAttribute('name');
        $ownerName = $profile->owner?->getAttribute('name');

        return [
            'id' => $profile->id,
            'person_id' => $profile->person_id,
            'customer_name' => is_string($personName) && $personName !== '' ? $personName : $profile->loyalty_member_number,
            'email' => $primaryEmail,
            'phone' => $primaryPhone,
            'owner_name' => is_string($ownerName) ? $ownerName : null,
            'golden_record_uid' => $profile->golden_record_uid,
            'region_code' => $profile->region_code,
            'home_outlet' => $profile->home_outlet,
            'privacy_regime' => $profile->privacy_regime,
            'consent_status' => $profile->consent_status,
            'loyalty_member_number' => $profile->loyalty_member_number,
            'loyalty_tier' => $profile->loyalty_tier,
            'loyalty_points' => (int) $profile->loyalty_points,
            'lifetime_spend_aud' => (float) $profile->lifetime_spend_aud,
            'is_primary_golden' => (bool) $profile->is_primary_golden,
            'merged_into_profile_id' => $profile->merged_into_profile_id,
            'last_synced_at' => $profile->last_synced_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIntegration(Integration $integration): array
    {
        return [
            'id' => $integration->id,
            'system_code' => $integration->system_code,
            'name' => $integration->name,
            'vendor_owner' => $integration->vendor_owner,
            'contract_version' => $integration->contract_version,
            'sync_mode' => $integration->sync_mode,
            'status' => $integration->status,
            'sla_target_ms' => (int) $integration->sla_target_ms,
            'last_latency_ms' => (int) $integration->last_latency_ms,
            'records_synced_24h' => (int) $integration->records_synced_24h,
            'failed_events_count' => (int) $integration->failed_events_count,
            'last_heartbeat_at' => $integration->last_heartbeat_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSyncEvent(SyncEvent $event): array
    {
        return [
            'id' => $event->id,
            'integration_id' => $event->integration_id,
            'system_code' => $event->integration->system_code,
            'system_name' => $event->integration->name,
            'region_code' => $event->region_code,
            'event_type' => $event->event_type,
            'external_ref' => $event->external_ref,
            'payload_hash' => substr($event->payload_hash, 0, 16),
            'status' => $event->status,
            'latency_ms' => (int) $event->latency_ms,
            'error_message' => $event->error_message,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTicket(ServiceTicket $ticket): array
    {
        $customerName = $ticket->person?->getAttribute('name');
        $ownerName = $ticket->owner?->getAttribute('name');

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'person_id' => $ticket->person_id,
            'customer_name' => is_string($customerName) ? $customerName : null,
            'user_id' => $ticket->user_id,
            'owner_name' => is_string($ownerName) ? $ownerName : null,
            'region_code' => $ticket->region_code,
            'channel' => $ticket->channel,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'subject' => $ticket->subject,
            'summary' => $ticket->summary,
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }
}
