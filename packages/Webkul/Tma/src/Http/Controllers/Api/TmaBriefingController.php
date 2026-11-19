<?php

namespace Webkul\Tma\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Tma\Models\UserPasskey;
use Webkul\User\Models\User;

class TmaBriefingController extends Controller
{
    public function show(ActionExecutor $executor): JsonResponse
    {
        /** @var User $user */
        $user = auth()->guard('user')->user();

        $executor->settleStale();

        $briefingOutcome = $executor->execute('briefing_today', []);
        $briefing = $briefingOutcome->httpStatus === 200
            ? ($briefingOutcome->body['result'] ?? [])
            : [];

        $canApprove = bouncer()->hasPermission(ActionExecutor::APPROVAL_PERMISSION);
        $authorizedIds = $canApprove ? bouncer()->getAuthorizedUserIds() : [$user->id];

        $pendingSlips = AgentActionCall::query()
            ->when($authorizedIds !== null, fn ($query) => $query->whereIn('user_id', $authorizedIds))
            ->where('status', AgentActionCall::PENDING_APPROVAL)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(static fn (AgentActionCall $call): array => [
                'id' => $call->id,
                'action' => $call->action,
                'params' => $call->params,
                'reason' => $call->reason,
                'subject' => $call->subject,
                'status' => $call->status,
                'created_at' => $call->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        $recentReceipts = AgentActionCall::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', AgentActionCall::PENDING_APPROVAL)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn (AgentActionCall $call): array => [
                'id' => $call->id,
                'action' => $call->action,
                'status' => $call->status,
                'decided_at' => $call->decided_at?->toIso8601String(),
                'created_at' => $call->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        $passkeys = UserPasskey::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get(['id', 'credential_id', 'label', 'alg', 'sign_count', 'last_used_at']);

        return new JsonResponse([
            'ok' => true,
            'briefing' => $briefing,
            'pending_slips' => $pendingSlips,
            'recent_receipts' => $recentReceipts,
            'streak' => $this->computeStreak($user->id),
            'vault' => $this->readVaultHead(),
            'passkeys' => $passkeys,
        ]);
    }

    /**
     * @return array{streak_days: int, signed_today: int, captured_today: int}
     */
    private function computeStreak(int $userId): array
    {
        $todayStart = Carbon::today();

        $signedToday = AgentActionCall::query()
            ->where(static function ($query) use ($userId): void {
                $query->where('decided_by', $userId)
                    ->orWhere('user_id', $userId);
            })
            ->whereIn('status', [AgentActionCall::COMPLETED, AgentActionCall::REJECTED])
            ->where('updated_at', '>=', $todayStart)
            ->count();

        $capturedToday = AgentActionCall::query()
            ->where('user_id', $userId)
            ->where('action', 'intake_capture')
            ->where('created_at', '>=', $todayStart)
            ->count();

        $activeDates = AgentActionCall::query()
            ->where('user_id', $userId)
            ->where('status', AgentActionCall::COMPLETED)
            ->where('created_at', '>=', Carbon::today()->subDays(30))
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(static fn ($ts): string => Carbon::parse($ts)->toDateString())
            ->unique()
            ->values()
            ->all();

        $streakDays = 0;
        $cursor = Carbon::today();

        if (! in_array($cursor->toDateString(), $activeDates, true)) {
            $cursor = $cursor->subDay();
        }

        while (in_array($cursor->toDateString(), $activeDates, true)) {
            $streakDays++;
            $cursor = $cursor->subDay();
        }

        return [
            'streak_days' => $streakDays,
            'signed_today' => $signedToday,
            'captured_today' => $capturedToday,
        ];
    }

    /**
     * @return array{head: array<string, mixed>|null, latest_epoch: array<string, mixed>|null}
     */
    private function readVaultHead(): array
    {
        $head = null;
        $latestEpoch = null;

        if (Schema::hasTable('data_blocks')) {
            $row = DB::table('data_blocks')->orderByDesc('block_height')->first();

            if ($row !== null) {
                $rowVars = get_object_vars($row);
                $head = [
                    'height' => isset($rowVars['block_height']) ? (int) $rowVars['block_height'] : 0,
                    'receipt_code' => (string) ($rowVars['receipt_code'] ?? ''),
                    'block_hash' => (string) ($rowVars['block_hash'] ?? ''),
                    'prev_hash' => (string) ($rowVars['prev_block_hash'] ?? ''),
                    'alg' => (string) ($rowVars['signer_alg'] ?? ''),
                    'action_type' => (string) ($rowVars['event_name'] ?? ''),
                    'created_at' => (string) ($rowVars['created_at'] ?? ''),
                ];
            }
        }

        if (Schema::hasTable('ledger_epochs')) {
            $epochRow = DB::table('ledger_epochs')->orderByDesc('id')->first();

            if ($epochRow !== null) {
                $epochVars = get_object_vars($epochRow);
                $latestEpoch = [
                    'id' => isset($epochVars['id']) ? (int) $epochVars['id'] : 0,
                    'start_height' => isset($epochVars['start_height']) ? (int) $epochVars['start_height'] : 0,
                    'end_height' => isset($epochVars['end_height']) ? (int) $epochVars['end_height'] : 0,
                    'merkle_root' => (string) ($epochVars['merkle_root'] ?? ''),
                    'anchor_driver' => (string) ($epochVars['anchor_driver'] ?? ''),
                    'anchor_proof' => $epochVars['anchor_reference'] ?? null,
                    'created_at' => (string) ($epochVars['sealed_at'] ?? ''),
                ];
            }
        }

        return [
            'head' => $head,
            'latest_epoch' => $latestEpoch,
        ];
    }
}
