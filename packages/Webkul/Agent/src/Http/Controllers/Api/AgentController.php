<?php

namespace Webkul\Agent\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentPreferenceSignal;
use Webkul\Agent\Repositories\AgentActionCallRepository;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Agent\Support\ActionOutcome;
use Webkul\Agent\Support\AgentContext;
use Webkul\Agent\Support\McpServer;
use Webkul\Agent\Support\PassportGuard;
use Webkul\Agent\Support\PlaybookMemory;

class AgentController extends Controller
{
    public function context(AgentContext $context): JsonResponse
    {
        return new JsonResponse($context->describe());
    }

    public function execute(Request $request, ActionExecutor $executor, string $action): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if ($key !== null && strlen($key) > 128) {
            return $this->respond(ActionOutcome::error(422, 'invalid_idempotency_key', 'The Idempotency-Key is longer than 128 characters.'));
        }

        $dryRun = filter_var($request->header('X-Luminara-Dry-Run'), FILTER_VALIDATE_BOOLEAN);

        return $this->respond($executor->execute($action, $request->json()->all(), $key, $dryRun));
    }

    public function call(
        AgentActionCallRepository $callRepository,
        ActionExecutor $executor,
        PassportGuard $passportGuard,
        int $id,
    ): JsonResponse {
        $executor->settleStale();

        $passport = $passportGuard->resolve();
        $criteria = ['id' => $id, 'user_id' => auth()->guard('user')->id()];

        if ($passport !== null) {
            $criteria['agent_passport_id'] = $passport->id;
        }

        $call = $callRepository->findOneWhere($criteria);

        return $this->respond($call
            ? ActionOutcome::fromCall($call)
            : ActionOutcome::error(404, 'not_found', 'You have made no call with this id.'));
    }

    public function events(
        Request $request,
        ActionExecutor $executor,
        PassportGuard $passportGuard,
        PlaybookMemory $playbook,
    ): JsonResponse {
        $executor->settleStale();

        $sinceId = max(0, (int) $request->query('since_id', 0));
        $limit = min(100, max(1, (int) $request->query('limit', 50)));
        $userId = auth()->guard('user')->id();
        $passport = $passportGuard->resolve();

        $calls = AgentActionCall::query()
            ->where('user_id', $userId)
            ->when($passport !== null, fn ($q) => $q->where('agent_passport_id', $passport->id))
            ->where('id', '>', $sinceId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $callIds = $calls->pluck('id')->all();
        $signals = Schema::hasTable('agent_preference_signals')
            ? AgentPreferenceSignal::query()
                ->where(function ($q) use ($userId, $passport, $callIds) {
                    if ($passport !== null) {
                        if ($callIds !== []) {
                            $q->whereIn('call_id', $callIds);
                        } else {
                            $q->whereRaw('1 = 0');
                        }

                        return;
                    }

                    $q->where('user_id', $userId);

                    if ($callIds !== []) {
                        $q->orWhereIn('call_id', $callIds);
                    }
                })
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (AgentPreferenceSignal $signal) => [
                    'id' => $signal->id,
                    'call_id' => $signal->call_id,
                    'action' => $signal->action,
                    'signal_type' => $signal->signal_type,
                    'param_diff' => $signal->param_diff,
                    'feedback_note' => $signal->feedback_note,
                    'distilled_rule' => $signal->distilled_rule,
                    'created_at' => $signal->created_at->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        $nextSinceId = $calls->isNotEmpty() ? (int) $calls->last()->id : $sinceId;

        return new JsonResponse([
            'ok' => true,
            'since_id' => $sinceId,
            'next_since_id' => $nextSinceId,
            'playbook_version' => $playbook->version(),
            'events' => $calls->map(fn (AgentActionCall $call) => [
                'call_id' => $call->id,
                'action' => $call->action,
                'status' => $call->status,
                'edited_params' => $call->edited_params,
                'human_feedback' => $call->human_feedback,
                'receipt_code' => $call->receipt_code,
                'block_hash' => $call->block_hash,
                'result' => $call->result,
                'error_code' => $call->error_code,
                'decided_at' => $call->decided_at?->toIso8601String(),
                'reviewed_at' => $call->reviewed_at?->toIso8601String(),
                'updated_at' => $call->updated_at->toIso8601String(),
            ])->values()->all(),
            'preference_signals' => $signals,
        ]);
    }

    public function mcp(Request $request, McpServer $server): JsonResponse|Response
    {
        $message = json_decode($request->getContent(), true);

        if (! is_array($message) || array_is_list($message)) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32600, 'message' => 'Send one JSON-RPC message per request.'],
            ], 400);
        }

        $response = $server->handle($message);

        return $response === null ? response()->noContent(202) : new JsonResponse($response);
    }

    private function respond(ActionOutcome $outcome): JsonResponse
    {
        return new JsonResponse($outcome->body, $outcome->httpStatus);
    }
}
