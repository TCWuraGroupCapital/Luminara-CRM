<?php

namespace Webkul\Gourwest\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Gourwest\Models\CustomerProfile;
use Webkul\Gourwest\Models\Integration;
use Webkul\Gourwest\Models\SyncEvent;
use Webkul\Security\Support\SecurityAuditRecorder;

class IntegrationSyncService
{
    /**
     * Canonical 8-system Gourwest enterprise integration architecture.
     *
     * @var array<string, array{
     *     system_code: string,
     *     name: string,
     *     vendor_owner: string,
     *     contract_version: string,
     *     sync_mode: string,
     *     sla_target_ms: int,
     *     default_latency_ms: int,
     *     default_records_24h: int
     * }>
     */
    public const SYSTEMS = [
        'erp' => [
            'system_code' => 'erp',
            'name' => 'Enterprise Resource Planning (ERP)',
            'vendor_owner' => 'Internal IT Division',
            'contract_version' => 'v2.4.0',
            'sync_mode' => 'cdc_stream',
            'sla_target_ms' => 3000,
            'default_latency_ms' => 64,
            'default_records_24h' => 48210,
        ],
        'pos' => [
            'system_code' => 'pos',
            'name' => 'Global Point of Sale (POS)',
            'vendor_owner' => 'Internal IT Division',
            'contract_version' => 'v3.1.0',
            'sync_mode' => 'real_time_webhook',
            'sla_target_ms' => 3000,
            'default_latency_ms' => 28,
            'default_records_24h' => 184920,
        ],
        'mobile_ordering' => [
            'system_code' => 'mobile_ordering',
            'name' => 'Gourwest Mobile Ordering App',
            'vendor_owner' => 'Global CRM Partners',
            'contract_version' => 'v2.2.0',
            'sync_mode' => 'real_time_webhook',
            'sla_target_ms' => 3000,
            'default_latency_ms' => 22,
            'default_records_24h' => 112640,
        ],
        'online_ordering' => [
            'system_code' => 'online_ordering',
            'name' => 'Global Web & Online Ordering',
            'vendor_owner' => 'Global CRM Partners',
            'contract_version' => 'v2.1.0',
            'sync_mode' => 'real_time_webhook',
            'sla_target_ms' => 3000,
            'default_latency_ms' => 35,
            'default_records_24h' => 76450,
        ],
        'marketing_automation' => [
            'system_code' => 'marketing_automation',
            'name' => 'Omnichannel Marketing Automation',
            'vendor_owner' => 'Global CRM Partners',
            'contract_version' => 'v1.9.0',
            'sync_mode' => 'cdc_stream',
            'sla_target_ms' => 3000,
            'default_latency_ms' => 89,
            'default_records_24h' => 63800,
        ],
        'loyalty_rewards' => [
            'system_code' => 'loyalty_rewards',
            'name' => 'Global Loyalty & Rewards Ledger',
            'vendor_owner' => 'Global CRM Partners',
            'contract_version' => 'v2.0.0',
            'sync_mode' => 'real_time_webhook',
            'sla_target_ms' => 3000,
            'default_latency_ms' => 18,
            'default_records_24h' => 149300,
        ],
        'customer_support' => [
            'system_code' => 'customer_support',
            'name' => 'Regional Customer Support & SLA Desk',
            'vendor_owner' => 'Global CRM Partners',
            'contract_version' => 'v1.8.0',
            'sync_mode' => 'real_time_webhook',
            'sla_target_ms' => 3000,
            'default_latency_ms' => 41,
            'default_records_24h' => 14280,
        ],
        'bi_reporting' => [
            'system_code' => 'bi_reporting',
            'name' => 'Executive BI & Data Warehouse',
            'vendor_owner' => 'Internal IT Division',
            'contract_version' => 'v1.5.0',
            'sync_mode' => 'scheduled_batch',
            'sla_target_ms' => 3000,
            'default_latency_ms' => 145,
            'default_records_24h' => 92500,
        ],
    ];

    public function resolveWebhookSecret(): string
    {
        $configured = trim((string) config('gourwest.webhook_secret', ''));

        if ($configured !== '') {
            return $configured;
        }

        return trim((string) config('app.key', ''));
    }

    public function verifySignature(string $rawPayload, ?string $signatureHeader): bool
    {
        $secret = $this->resolveWebhookSecret();

        if ($secret === '' || $signatureHeader === null) {
            return false;
        }

        $trimmed = trim($signatureHeader);

        if ($trimmed === '') {
            return false;
        }

        $provided = str_starts_with(strtolower($trimmed), 'sha256=')
            ? substr($trimmed, 7)
            : $trimmed;

        if (! preg_match('/^[a-f0-9]{64}$/i', $provided)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, strtolower($provided));
    }

    /**
     * Seed or refresh all 8 business system contracts plus baseline sync events and 2 replayable dead-letters.
     *
     * @return array{integrations_count: int, events_count: int, dead_letters_count: int}
     */
    public function seedEnterpriseIntegrationsAndEvents(bool $resetDeadLetters = true): array
    {
        $integrationsByCode = [];

        foreach (self::SYSTEMS as $code => $spec) {
            /** @var Integration $integration */
            $integration = Integration::query()->updateOrCreate(
                ['system_code' => $code],
                [
                    'name' => $spec['name'],
                    'vendor_owner' => $spec['vendor_owner'],
                    'contract_version' => $spec['contract_version'],
                    'sync_mode' => $spec['sync_mode'],
                    'sla_target_ms' => $spec['sla_target_ms'],
                    'last_latency_ms' => $spec['default_latency_ms'],
                    'records_synced_24h' => $spec['default_records_24h'],
                    'last_heartbeat_at' => now()->subSeconds(random_int(5, 45)),
                ]
            );

            $integrationsByCode[$code] = $integration;
        }

        $seedEvents = [
            [
                'code' => 'pos',
                'external_ref' => 'GW-POS-AU-900101',
                'region_code' => 'AU',
                'event_type' => 'pos.order.completed',
                'status' => 'processed',
                'latency_ms' => 24,
                'error_message' => null,
            ],
            [
                'code' => 'mobile_ordering',
                'external_ref' => 'GW-MOB-SG-900102',
                'region_code' => 'SG',
                'event_type' => 'mobile.order.placed',
                'status' => 'processed',
                'latency_ms' => 19,
                'error_message' => null,
            ],
            [
                'code' => 'online_ordering',
                'external_ref' => 'GW-WEB-UK-900103',
                'region_code' => 'UK',
                'event_type' => 'web.checkout.captured',
                'status' => 'processed',
                'latency_ms' => 33,
                'error_message' => null,
            ],
            [
                'code' => 'erp',
                'external_ref' => 'GW-ERP-NZ-900104',
                'region_code' => 'NZ',
                'event_type' => 'erp.invoice.reconciled',
                'status' => 'processed',
                'latency_ms' => 58,
                'error_message' => null,
            ],
            [
                'code' => 'marketing_automation',
                'external_ref' => 'GW-MKT-CA-900105',
                'region_code' => 'CA',
                'event_type' => 'consent.pipeda.verified',
                'status' => 'processed',
                'latency_ms' => 82,
                'error_message' => null,
            ],
            [
                'code' => 'customer_support',
                'external_ref' => 'GW-SUP-MY-900106',
                'region_code' => 'MY',
                'event_type' => 'sla.ticket.escalated',
                'status' => 'processed',
                'latency_ms' => 39,
                'error_message' => null,
            ],
            [
                'code' => 'bi_reporting',
                'external_ref' => 'GW-BI-GLOBAL-900107',
                'region_code' => 'AU',
                'event_type' => 'bi.kpi_snapshot.published',
                'status' => 'processed',
                'latency_ms' => 141,
                'error_message' => null,
            ],
            [
                'code' => 'pos',
                'external_ref' => 'GW-DLQ-TH-900901',
                'region_code' => 'TH',
                'event_type' => 'pos.offline_terminal.flush',
                'status' => 'dead_letter',
                'latency_ms' => 1840,
                'error_message' => 'Bangkok Sukhumvit Terminal #04 transient TLS upstream timeout; queued for idempotent replay.',
            ],
            [
                'code' => 'loyalty_rewards',
                'external_ref' => 'GW-DLQ-UK-900902',
                'region_code' => 'UK',
                'event_type' => 'loyalty.points.cross_border_credit',
                'status' => 'dead_letter',
                'latency_ms' => 920,
                'error_message' => 'Legacy GBP->AUD FX rate lock expired before ledger commit; ready for deterministic replay.',
            ],
        ];

        foreach ($seedEvents as $item) {
            $integration = $integrationsByCode[$item['code']];
            $existing = SyncEvent::query()
                ->where('integration_id', $integration->id)
                ->where('external_ref', $item['external_ref'])
                ->first();

            $targetStatus = $item['status'];

            if (! $resetDeadLetters && $existing !== null && $existing->status === 'replayed') {
                $targetStatus = 'replayed';
            }

            SyncEvent::query()->updateOrCreate(
                [
                    'integration_id' => $integration->id,
                    'external_ref' => $item['external_ref'],
                ],
                [
                    'region_code' => $item['region_code'],
                    'event_type' => $item['event_type'],
                    'payload_hash' => hash('sha256', $item['code'].'|'.$item['external_ref'].'|'.$item['event_type']),
                    'status' => $targetStatus,
                    'latency_ms' => $item['latency_ms'],
                    'error_message' => $targetStatus === 'dead_letter' ? $item['error_message'] : null,
                ]
            );
        }

        $this->recalculateIntegrationHealth();

        return [
            'integrations_count' => Integration::query()->count(),
            'events_count' => SyncEvent::query()->count(),
            'dead_letters_count' => SyncEvent::query()->where('status', 'dead_letter')->count(),
        ];
    }

    /**
     * Ingest an HMAC-authenticated external webhook payload for one of the 8 business systems.
     *
     * Idempotent per (system, external_ref): a repeated reference changes nothing and replays the first event.
     *
     * @param  array<string, mixed>  $payload
     * @return array{integration: Integration, event: SyncEvent, customer_updated: bool, duplicate: bool, member: array{points_balance: int, tier: string}|null}
     */
    public function ingestWebhook(string $systemCode, array $payload, string $rawBody): array
    {
        $startedNs = hrtime(true);
        $normalizedCode = strtolower(trim($systemCode));

        if (! isset(self::SYSTEMS[$normalizedCode])) {
            throw (new ModelNotFoundException)->setModel(Integration::class, [$normalizedCode]);
        }

        $externalRef = isset($payload['external_ref']) && is_string($payload['external_ref']) && trim($payload['external_ref']) !== ''
            ? substr(trim($payload['external_ref']), 0, 80)
            : 'WH-'.strtoupper($normalizedCode).'-'.strtoupper(Str::random(10));

        try {
            return $this->applyWebhook($normalizedCode, $externalRef, $payload, $rawBody, $startedNs);
        } catch (UniqueConstraintViolationException) {
            // A concurrent delivery of the same reference committed first; this one was rolled back in full.
            $integration = Integration::query()->where('system_code', $normalizedCode)->firstOrFail();

            return $this->replayDuplicate($integration, $externalRef);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{integration: Integration, event: SyncEvent, customer_updated: bool, duplicate: bool, member: array{points_balance: int, tier: string}|null}
     */
    private function applyWebhook(string $normalizedCode, string $externalRef, array $payload, string $rawBody, int $startedNs): array
    {
        return DB::transaction(function () use ($normalizedCode, $externalRef, $payload, $rawBody, $startedNs): array {
            $spec = self::SYSTEMS[$normalizedCode];

            /** @var Integration $integration */
            $integration = Integration::query()->firstOrCreate(
                ['system_code' => $normalizedCode],
                [
                    'name' => $spec['name'],
                    'vendor_owner' => $spec['vendor_owner'],
                    'contract_version' => $spec['contract_version'],
                    'sync_mode' => $spec['sync_mode'],
                    'status' => 'healthy',
                    'sla_target_ms' => $spec['sla_target_ms'],
                    'last_latency_ms' => $spec['default_latency_ms'],
                    'records_synced_24h' => 0,
                    'failed_events_count' => 0,
                    'last_heartbeat_at' => now(),
                ]
            );

            if (SyncEvent::query()->where('integration_id', $integration->id)->where('external_ref', $externalRef)->exists()) {
                return $this->replayDuplicate($integration, $externalRef);
            }

            $rawRegion = isset($payload['region_code']) && is_string($payload['region_code'])
                ? strtoupper(trim($payload['region_code']))
                : 'AU';
            $regionCode = RegionCatalog::isSupported($rawRegion) ? $rawRegion : 'AU';

            $eventType = isset($payload['event_type']) && is_string($payload['event_type']) && trim($payload['event_type']) !== ''
                ? substr(trim($payload['event_type']), 0, 64)
                : $normalizedCode.'.webhook.received';

            $customerUpdated = false;
            $member = null;

            if (isset($payload['loyalty_member_number']) && is_string($payload['loyalty_member_number'])) {
                /** @var CustomerProfile|null $profile */
                $profile = CustomerProfile::query()
                    ->where('loyalty_member_number', trim($payload['loyalty_member_number']))
                    ->lockForUpdate()
                    ->first();

                if ($profile !== null) {
                    $pointsDelta = isset($payload['points_delta']) ? (int) $payload['points_delta'] : 0;
                    $spendDelta = isset($payload['spend_aud']) ? (float) $payload['spend_aud'] : 0.0;
                    $nextPoints = max(0, (int) $profile->loyalty_points + $pointsDelta);
                    $nextSpend = max(0.0, (float) $profile->lifetime_spend_aud + $spendDelta);
                    $member = ['points_balance' => $nextPoints, 'tier' => RegionCatalog::tierForPoints($nextPoints)];

                    $profile->update([
                        'loyalty_points' => $nextPoints,
                        'loyalty_tier' => $member['tier'],
                        'lifetime_spend_aud' => round($nextSpend, 2),
                        'last_synced_at' => now(),
                    ]);

                    $customerUpdated = true;
                }
            }

            $elapsedMs = max(1, (int) round((hrtime(true) - $startedNs) / 1_000_000));

            /** @var SyncEvent $event */
            $event = SyncEvent::query()->create([
                'integration_id' => $integration->id,
                'region_code' => $regionCode,
                'event_type' => $eventType,
                'external_ref' => $externalRef,
                'payload_hash' => hash('sha256', $rawBody),
                'status' => 'processed',
                'latency_ms' => $elapsedMs,
                'error_message' => null,
            ]);

            $deadLetterCount = SyncEvent::query()
                ->where('integration_id', $integration->id)
                ->where('status', 'dead_letter')
                ->count();

            $integration->update([
                'last_latency_ms' => $elapsedMs,
                'records_synced_24h' => (int) $integration->records_synced_24h + 1,
                'failed_events_count' => $deadLetterCount,
                'status' => $deadLetterCount > 0 ? 'degraded' : 'healthy',
                'last_heartbeat_at' => now(),
            ]);

            SecurityAuditRecorder::record(
                event: 'gourwest.webhook.ingested',
                auditableType: 'gourwest_integration',
                auditableId: $integration->id,
                metadata: [
                    'system_code' => $normalizedCode,
                    'region_code' => $regionCode,
                    'event_type' => $eventType,
                    'external_ref' => $externalRef,
                    'latency_ms' => $elapsedMs,
                    'customer_updated' => $customerUpdated,
                ]
            );

            return [
                'integration' => $integration->fresh() ?? $integration,
                'event' => $event,
                'customer_updated' => $customerUpdated,
                'duplicate' => false,
                'member' => $member,
            ];
        });
    }

    /**
     * @return array{integration: Integration, event: SyncEvent, customer_updated: bool, duplicate: bool, member: array{points_balance: int, tier: string}|null}
     */
    private function replayDuplicate(Integration $integration, string $externalRef): array
    {
        /** @var SyncEvent $event */
        $event = SyncEvent::query()
            ->where('integration_id', $integration->id)
            ->where('external_ref', $externalRef)
            ->firstOrFail();

        SecurityAuditRecorder::record(
            event: 'gourwest.webhook.duplicate_ignored',
            auditableType: 'gourwest_integration',
            auditableId: $integration->id,
            metadata: [
                'system_code' => $integration->system_code,
                'external_ref' => $externalRef,
                'original_event_id' => $event->id,
            ]
        );

        return [
            'integration' => $integration,
            'event' => $event,
            'customer_updated' => false,
            'duplicate' => true,
            'member' => null,
        ];
    }

    /**
     * Trigger a live health and synchronization pulse on a specific integration contract.
     *
     * @return array{integration: Integration, event: SyncEvent}
     */
    public function triggerSyncPulse(Integration $integration, ?string $regionCode = null): array
    {
        $startedNs = hrtime(true);
        $resolvedRegion = $regionCode !== null && RegionCatalog::isSupported($regionCode)
            ? strtoupper(trim($regionCode))
            : 'AU';

        $regionalProfiles = CustomerProfile::query()
            ->where('region_code', $resolvedRegion)
            ->where('is_primary_golden', true)
            ->count();

        $elapsedMs = max(1, (int) round((hrtime(true) - $startedNs) / 1_000_000));
        $externalRef = 'SYNC-'.strtoupper($integration->system_code).'-'.strtoupper(Str::random(8));

        /** @var SyncEvent $event */
        $event = SyncEvent::query()->create([
            'integration_id' => $integration->id,
            'region_code' => $resolvedRegion,
            'event_type' => $integration->system_code.'.heartbeat.verified',
            'external_ref' => $externalRef,
            'payload_hash' => hash('sha256', $integration->system_code.'|'.$externalRef.'|'.$regionalProfiles),
            'status' => 'processed',
            'latency_ms' => $elapsedMs,
            'error_message' => null,
        ]);

        $deadLetterCount = SyncEvent::query()
            ->where('integration_id', $integration->id)
            ->where('status', 'dead_letter')
            ->count();

        $integration->update([
            'last_latency_ms' => $elapsedMs,
            'records_synced_24h' => (int) $integration->records_synced_24h + max(1, $regionalProfiles),
            'failed_events_count' => $deadLetterCount,
            'status' => $deadLetterCount > 0 ? 'degraded' : 'healthy',
            'last_heartbeat_at' => now(),
        ]);

        return [
            'integration' => $freshIntegration = ($integration->fresh() ?? $integration),
            'event' => $event->setRelation('integration', $freshIntegration),
        ];
    }

    /**
     * Replay all dead-letter sync events cleanly and restore healthy connector status.
     *
     * @return array{replayed_count: int, integrations_healed: int}
     */
    public function replayDeadLetters(?int $integrationId = null): array
    {
        return DB::transaction(function () use ($integrationId): array {
            $query = SyncEvent::query()->where('status', 'dead_letter');

            if ($integrationId !== null) {
                $query->where('integration_id', $integrationId);
            }

            $deadLetters = $query->lockForUpdate()->get();

            if ($deadLetters->isEmpty()) {
                return [
                    'replayed_count' => 0,
                    'integrations_healed' => 0,
                ];
            }

            $startedNs = hrtime(true);
            $affectedIntegrationIds = [];

            foreach ($deadLetters as $event) {
                $affectedIntegrationIds[$event->integration_id] = true;
                $latencyMs = max(1, (int) round((hrtime(true) - $startedNs) / 1_000_000));

                $event->update([
                    'status' => 'replayed',
                    'latency_ms' => $latencyMs,
                    'error_message' => null,
                ]);
            }

            $this->recalculateIntegrationHealth();

            SecurityAuditRecorder::record(
                event: 'gourwest.sync.dead_letters_replayed',
                auditableType: 'gourwest_integration',
                auditableId: $integrationId,
                metadata: [
                    'replayed_count' => $deadLetters->count(),
                    'integration_ids' => array_keys($affectedIntegrationIds),
                ]
            );

            return [
                'replayed_count' => $deadLetters->count(),
                'integrations_healed' => count($affectedIntegrationIds),
            ];
        });
    }

    public function recalculateIntegrationHealth(): void
    {
        /** @var Collection<int, Integration> $integrations */
        $integrations = Integration::query()->get();

        foreach ($integrations as $integration) {
            $deadLetters = SyncEvent::query()
                ->where('integration_id', $integration->id)
                ->where('status', 'dead_letter')
                ->count();

            $integration->update([
                'failed_events_count' => $deadLetters,
                'status' => $deadLetters > 0 ? 'degraded' : 'healthy',
            ]);
        }
    }
}
