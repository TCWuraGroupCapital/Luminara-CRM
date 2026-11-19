<?php

namespace Webkul\Agent\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

/**
 * Shared read model for Owner Simple Mode (`/admin/agent/briefing`), the `briefing_today` action,
 * and MCP resources (`luminara://briefing/today`, `luminara://ledger/head`).
 */
final class BriefingData
{
    public const UNREVIEWED_ACTIONS = [
        'leads_create',
        'leads_update',
        'intake_capture',
        'contacts_upsert_person',
        'contacts_upsert_organization',
        'quotes_draft',
        'activities_log_note',
        'activities_schedule',
    ];

    public function __construct(
        private PlaybookMemory $playbook,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        /** @var User|null $user */
        $user = auth()->guard('user')->user();
        $userIds = bouncer()->getAuthorizedUserIds();

        $callScope = fn (Builder $query) => $userIds !== null
            ? $query->whereIn('user_id', $userIds)
            : $query;

        $pendingQuery = $callScope(AgentActionCall::query()->where('status', AgentActionCall::PENDING_APPROVAL));
        $pendingCount = (clone $pendingQuery)->count();
        /** @var Collection<int, AgentActionCall> $pendingModels */
        $pendingModels = (clone $pendingQuery)
            ->with(['user:id,name', 'passport:id,slug,name'])
            ->latest('id')
            ->limit(10)
            ->get();
        $pendingSlips = $pendingModels
            ->map(fn (AgentActionCall $call): array => [
                'id' => $call->id,
                'action' => $call->action,
                'params' => $call->params,
                'reason' => $call->reason,
                'subject' => $call->subject,
                'actor' => $call->getRelationValue('user')?->getAttribute('name') ?? $call->actor_email_snapshot,
                'passport' => $call->getRelationValue('passport')?->slug,
                'created_at' => $call->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        $unreviewedQuery = $callScope(
            AgentActionCall::query()
                ->where('status', AgentActionCall::COMPLETED)
                ->whereNull('reviewed_at')
                ->whereIn('action', self::UNREVIEWED_ACTIONS)
        );
        $unreviewedCount = (clone $unreviewedQuery)->count();
        /** @var Collection<int, AgentActionCall> $unreviewedModels */
        $unreviewedModels = (clone $unreviewedQuery)
            ->latest('id')
            ->limit(10)
            ->get();
        $unreviewedItems = $unreviewedModels
            ->map(fn (AgentActionCall $call): array => [
                'id' => $call->id,
                'action' => $call->action,
                'params' => $call->params,
                'result' => $call->result,
                'receipt_code' => $call->receipt_code,
                'created_at' => $call->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        $leadsSummary = $this->leadsSummary($userIds);
        $activitiesToday = $this->activitiesToday($userIds);
        $momentum = $this->momentum($user, $callScope);

        return [
            'date' => now()->toDateString(),
            'pending_approvals' => [
                'count' => $pendingCount,
                'items' => $pendingSlips,
            ],
            'unreviewed_creates' => [
                'count' => $unreviewedCount,
                'items' => $unreviewedItems,
            ],
            'leads' => $leadsSummary,
            'due_activities' => $activitiesToday,
            'momentum' => $momentum,
            'playbook' => [
                'version' => $this->playbook->version(),
                'blocks' => $this->playbook->allBlocks(),
            ],
            'ledger' => $this->ledgerHead(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ledgerHead(): array
    {
        if (! Schema::hasTable('data_blocks')) {
            return [
                'enabled' => class_exists('Webkul\\Ledger\\Support\\LedgerAppender'),
                'height' => 0,
                'head_hash' => null,
                'latest_receipt' => null,
                'latest_epoch_root' => null,
            ];
        }

        $head = DB::table('data_blocks')->orderByDesc('id')->first();
        $height = (int) DB::table('data_blocks')->count();
        $epoch = Schema::hasTable('ledger_epochs')
            ? DB::table('ledger_epochs')->orderByDesc('id')->first()
            : null;

        return [
            'enabled' => true,
            'height' => $height,
            'head_hash' => $head->block_hash ?? null,
            'latest_receipt' => $head->receipt_code ?? null,
            'latest_epoch_root' => $epoch->merkle_root ?? ($epoch->root_hash ?? null),
        ];
    }

    /**
     * @param  array<int, int>|null  $userIds
     * @return array<string, mixed>
     */
    private function leadsSummary(?array $userIds): array
    {
        if (! bouncer()->hasPermission('leads')) {
            return [
                'open_count' => 0,
                'open_value' => 0.0,
                'rotting_count' => 0,
                'rotting_items' => [],
            ];
        }

        $openQuery = Lead::query()
            ->select('leads.*')
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNotIn('lead_pipeline_stages.code', ['won', 'lost'])
            ->when($userIds !== null, fn (Builder $q) => $q->whereIn('leads.user_id', $userIds));

        $openCount = (clone $openQuery)->count();
        $openValue = (float) (clone $openQuery)->sum('leads.lead_value');

        $rottingQuery = (clone $openQuery)->where('leads.updated_at', '<', now()->subDays(7));
        $rottingCount = (clone $rottingQuery)->count();
        $rottingItems = (clone $rottingQuery)
            ->with('stage:id,name')
            ->oldest('leads.updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Lead $lead): array => [
                'id' => $lead->getKey(),
                'title' => $lead->getAttribute('title'),
                'lead_value' => $lead->getAttribute('lead_value') !== null ? (float) $lead->getAttribute('lead_value') : null,
                'stage' => $lead->getRelationValue('stage')?->getAttribute('name'),
                'updated_at' => $lead->getAttribute('updated_at')?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'open_count' => $openCount,
            'open_value' => round($openValue, 2),
            'rotting_count' => $rottingCount,
            'rotting_items' => $rottingItems,
        ];
    }

    /**
     * @param  array<int, int>|null  $userIds
     * @return array<string, mixed>
     */
    private function activitiesToday(?array $userIds): array
    {
        if (! bouncer()->hasPermission('activities')) {
            return ['count' => 0, 'items' => []];
        }

        $query = Activity::query()
            ->where('is_done', 0)
            ->where(function (Builder $q) {
                $q->whereNull('schedule_from')
                    ->orWhere('schedule_from', '<=', now()->endOfDay());
            })
            ->when($userIds !== null, function (Builder $q) use ($userIds) {
                $q->where(function (Builder $sub) use ($userIds) {
                    $sub->whereIn('activities.user_id', $userIds)
                        ->orWhereExists(function ($exists) use ($userIds) {
                            $exists->select(DB::raw(1))
                                ->from('activity_participants')
                                ->whereColumn('activity_participants.activity_id', 'activities.id')
                                ->whereIn('activity_participants.user_id', $userIds);
                        });
                });
            });

        $count = (clone $query)->count();
        $items = (clone $query)
            ->orderBy('schedule_from')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->getKey(),
                'title' => $activity->getAttribute('title'),
                'type' => $activity->getAttribute('type'),
                'schedule_from' => $activity->getAttribute('schedule_from') ? (string) $activity->getAttribute('schedule_from') : null,
            ])
            ->values()
            ->all();

        return ['count' => $count, 'items' => $items];
    }

    /**
     * @param  callable(Builder): Builder  $callScope
     * @return array<string, int>
     */
    private function momentum(?User $user, callable $callScope): array
    {
        $completed7d = $callScope(
            AgentActionCall::query()
                ->where('status', AgentActionCall::COMPLETED)
                ->where('created_at', '>=', now()->subDays(7))
        )->count();

        $reviewed7d = $callScope(
            AgentActionCall::query()
                ->whereNotNull('reviewed_at')
                ->where('reviewed_at', '>=', now()->subDays(7))
        )->count();

        $passportStreak = ($user instanceof User && Schema::hasTable('agent_passports'))
            ? (int) AgentPassport::query()->where('user_id', $user->id)->sum('approved_streak')
            : 0;

        return [
            'completed_7d' => $completed7d,
            'reviewed_7d' => $reviewed7d,
            'approved_streak' => $passportStreak,
        ];
    }
}
