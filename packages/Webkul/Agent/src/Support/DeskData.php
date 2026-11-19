<?php

namespace Webkul\Agent\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\User\Models\User;

/**
 * Everything the Desk shows, read as the signed-in user: the default pipeline with each stage's totals
 * and largest deals, the agent calls waiting for a person, and what the agent did for this user today.
 */
final class DeskData
{
    /**
     * Deals drawn per stage. Deals a waiting call would change are always added on top.
     */
    public const DEALS_PER_STAGE = 10;

    private const DECIDABLE = ['leads_move_stage', 'leads_delete'];

    public function __construct(
        private PipelineRepository $pipelineRepository,
        private LeadRepository $leadRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        /** @var Model|null $pipeline */
        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $canApprove = bouncer()->hasPermission(ActionExecutor::APPROVAL_PERMISSION);
        $proposals = $canApprove ? $this->proposals() : [];

        return [
            'pipeline' => $pipeline ? ['id' => $pipeline->getKey(), 'name' => $pipeline->getAttribute('name')] : null,
            'stages' => $pipeline ? $this->stages($pipeline->getRelationValue('stages'), array_merge(...array_column($proposals, 'deal_ids'))) : [],
            'proposals' => $proposals,
            'activity' => $this->activity($user),
            'can' => [
                'approve' => $canApprove,
                'move' => bouncer()->hasPermission('leads.edit'),
                'view' => bouncer()->hasPermission('leads.view'),
            ],
        ];
    }

    /**
     * @param  iterable<int, Model>  $stages
     * @param  array<int, int>  $pinned
     * @return array<int, array<string, mixed>>
     */
    private function stages(iterable $stages, array $pinned): array
    {
        $rows = [];

        foreach ($stages as $stage) {
            /** @return Builder<Lead> */
            $scoped = fn (): Builder => bouncer()->scopeToAuthorizedUsers($this->leadRepository->getModel()->newQuery())
                ->where('lead_pipeline_stage_id', $stage->getKey());

            /** @var Collection<int, Lead> $deals */
            $deals = $scoped()->with(['person.organization', 'user'])
                ->where(fn ($query) => $query
                    ->whereIn('id', $scoped()->orderByDesc('lead_value')->limit(self::DEALS_PER_STAGE)->pluck('id'))
                    ->orWhereIn('id', $pinned))
                ->orderByDesc('lead_value')
                ->get();

            $rows[] = [
                'id' => $stage->getKey(),
                'code' => $stage->getAttribute('code'),
                'name' => $stage->getAttribute('name'),
                'count' => $scoped()->count(),
                'value' => (float) $scoped()->sum('lead_value'),
                'deals' => $deals->map(fn (Lead $lead): array => $this->deal($lead))->all(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function deal(Lead $lead): array
    {
        $person = $lead->getRelationValue('person');
        $title = $lead->getAttribute('title');

        return [
            'id' => $lead->getKey(),
            'title' => $title,
            'value' => (float) $lead->getAttribute('lead_value'),
            'company' => $person?->getRelationValue('organization')?->getAttribute('name') ?? $person?->getAttribute('name') ?? $title,
            'person' => $person?->getAttribute('name'),
            'owner' => $lead->getRelationValue('user')?->getAttribute('name'),
            'url' => route('admin.leads.view', $lead->getKey()),
        ];
    }

    /**
     * Calls waiting for a person, in the shape the Desk draws: which deals they touch and where to.
     *
     * @return array<int, array<string, mixed>>
     */
    private function proposals(): array
    {
        $ttl = (int) config('agent.approval_ttl_hours');
        $userIds = bouncer()->getAuthorizedUserIds();
        $rows = [];

        $calls = AgentActionCall::query()
            ->with('user')
            ->where('status', AgentActionCall::PENDING_APPROVAL)
            ->whereIn('action', self::DECIDABLE)
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds))
            ->orderBy('id')
            ->get();

        foreach ($calls as $call) {
            $rows[] = [
                'id' => $call->id,
                'action' => $call->action,
                'type' => $call->action === 'leads_delete' ? 'remove' : 'move',
                'deal_ids' => array_map('intval', array_column($call->subject ?? [], 'id')),
                'to_stage_id' => $call->params['stage_id'] ?? null,
                'subject' => $call->subject ?? [],
                'reason' => $call->reason,
                'requested_at' => $call->created_at->toIso8601String(),
                'closes_at' => $call->created_at->copy()->addHours($ttl)->toIso8601String(),
                'acting_as' => $call->user?->getAttribute('name'),
                'via' => $call->token_id ? 'token' : 'assistant',
                'approve_url' => route('admin.agent.approvals.approve', $call->id),
                'reject_url' => route('admin.agent.approvals.reject', $call->id),
            ];
        }

        return $rows;
    }

    /**
     * Today's calls made as this user, oldest first, with just what a sentence about each needs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activity(User $user): array
    {
        $rows = [];

        $calls = AgentActionCall::query()
            ->where('user_id', $user->getKey())
            ->where('created_at', '>=', now()->startOfDay())
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($calls as $call) {
            $rows[] = [
                'id' => $call->id,
                'action' => $call->action,
                'status' => $call->status,
                'at' => $call->created_at->toIso8601String(),
                'query' => $call->params['query'] ?? null,
                'lead_id' => $call->params['lead_id'] ?? null,
                'title' => $call->result['lead']['title'] ?? $call->params['title'] ?? $call->subject[0]['title'] ?? null,
                'count' => count($call->params['lead_ids'] ?? []) ?: null,
                'stage' => $call->result['stage'] ?? $call->subject[0]['moves_to'] ?? null,
            ];
        }

        return $rows;
    }
}
