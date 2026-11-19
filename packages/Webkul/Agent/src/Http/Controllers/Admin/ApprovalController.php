<?php

namespace Webkul\Agent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Repositories\AgentActionCallRepository;
use Webkul\Agent\Support\ActionExecutor;

class ApprovalController extends Controller
{
    public function __construct(
        private AgentActionCallRepository $callRepository,
        private ActionExecutor $executor,
    ) {}

    public function index(): View
    {
        $this->authorizeApprover();

        $this->executor->settleStale();
        $userIds = bouncer()->getAuthorizedUserIds();

        /**
         * Calls waiting on a person come first; everything else follows as run history.
         */
        $calls = $this->callRepository->scopeQuery(fn ($query) => $query
            ->with(['user', 'passport'])
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds))
            ->orderByRaw('status = ? desc', [AgentActionCall::PENDING_APPROVAL])
            ->orderByDesc('id')
        )->paginate();

        return view('agent::approvals.index', ['calls' => $calls]);
    }

    public function approve(Request $request, int $id): RedirectResponse|JsonResponse
    {
        return $this->decide($request, $id, true);
    }

    public function reject(Request $request, int $id): RedirectResponse|JsonResponse
    {
        return $this->decide($request, $id, false);
    }

    public function review(Request $request, int $id): RedirectResponse|JsonResponse
    {
        /** @var AgentActionCall $call */
        $call = $this->callRepository->findOrFail($id);
        $userIds = bouncer()->getAuthorizedUserIds();
        $inTeamScope = $userIds === null || ($call->user_id !== null && in_array((int) $call->user_id, $userIds, true));

        abort_unless(
            (bouncer()->hasPermission(ActionExecutor::APPROVAL_PERMISSION) && $inTeamScope)
            || ($call->user_id !== null && (int) $call->user_id === (int) auth()->guard('user')->id()),
            401,
            trans('admin::app.errors.unauthorized')
        );

        $updated = $this->executor->markReviewed($call);

        if ($request->wantsJson()) {
            return new JsonResponse([
                'ok' => true,
                'call_id' => $updated->id,
                'reviewed_at' => $updated->reviewed_at?->toIso8601String(),
            ]);
        }

        session()->flash('success', trans('agent::app.approvals.reviewed-toast'));

        return redirect()->back(fallback: route('admin.agent.approvals.index'));
    }

    private function decide(Request $request, int $id, bool $approve): RedirectResponse|JsonResponse
    {
        $this->authorizeApprover();

        /** @var AgentActionCall $call */
        $call = $this->callRepository->findOrFail($id);
        $userIds = bouncer()->getAuthorizedUserIds();

        abort_unless(
            $userIds === null || ($call->user_id !== null && in_array((int) $call->user_id, $userIds, true)),
            401,
            trans('admin::app.errors.unauthorized')
        );

        $editedParams = null;

        if ($approve) {
            if (is_array($request->input('edited_params'))) {
                $editedParams = $request->input('edited_params');
            } elseif ($request->filled('edited_params_json')) {
                $decoded = json_decode((string) $request->input('edited_params_json'), true);

                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
                    return $this->decisionResponse('invalid_params', trans('agent::app.approvals.outcome.invalid_params'), 422);
                }

                if ($decoded != $call->params) {
                    $editedParams = $decoded;
                }
            }
        }

        $feedbackNote = $request->filled('feedback_note') ? (string) $request->input('feedback_note') : null;
        $rawProof = $request->input('signer_proof');
        $sanitizedProof = is_array($rawProof)
            ? array_diff_key($rawProof, array_flip([
                'alg', 'pubkey', 'proof', 'signature', 'signed_data',
                'message', 'message_b64', 'webauthn_verified', 'envelope',
            ]))
            : [];
        $signerProof = $sanitizedProof !== [] ? $sanitizedProof : null;
        $distillRule = $request->boolean('distill_rule');

        $outcome = $this->executor->decide($call, $approve, $editedParams, $feedbackNote, $signerProof, $distillRule);

        $status = (string) ($outcome->body['status'] ?? $outcome->body['error']['code']);
        $done = in_array($status, [AgentActionCall::COMPLETED, AgentActionCall::REJECTED], true);
        $message = trans('agent::app.approvals.outcome.'.$status);
        $httpStatus = $done ? 200 : match ($status) {
            'invalid_params' => 422,
            'forbidden' => 403,
            default => 409,
        };

        return $this->decisionResponse($status, $message, $httpStatus, $done, $outcome->body['receipt_code'] ?? null);
    }

    private function decisionResponse(
        string $status,
        string $message,
        int $httpStatus,
        bool $done = false,
        ?string $receiptCode = null,
    ): RedirectResponse|JsonResponse {
        // The Desk decides in place and animates the result, so it asks for JSON instead of a redirect.
        if (request()->wantsJson()) {
            return new JsonResponse(array_filter([
                'status' => $status,
                'message' => $message,
                'receipt_code' => $receiptCode,
            ], fn ($v) => $v !== null), $httpStatus);
        }

        session()->flash($done ? 'success' : 'error', $message);

        return redirect()->route('admin.agent.approvals.index');
    }

    private function authorizeApprover(): void
    {
        abort_unless(
            bouncer()->hasPermission(ActionExecutor::APPROVAL_PERMISSION),
            401,
            trans('admin::app.errors.unauthorized')
        );
    }
}
