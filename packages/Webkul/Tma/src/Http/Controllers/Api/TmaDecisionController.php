<?php

namespace Webkul\Tma\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Repositories\AgentActionCallRepository;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Agent\Support\ActionOutcome;
use Webkul\Tma\Exceptions\TmaAuthException;
use Webkul\Tma\Models\UserPasskey;
use Webkul\Tma\Support\WebAuthnVerifier;
use Webkul\User\Models\User;

class TmaDecisionController extends Controller
{
    public function decide(
        Request $request,
        AgentActionCallRepository $callRepository,
        ActionExecutor $executor,
        WebAuthnVerifier $verifier,
        int $id,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->guard('user')->user();

        if (! bouncer()->hasPermission(ActionExecutor::APPROVAL_PERMISSION)) {
            $forbidden = ActionOutcome::error(
                403,
                'forbidden',
                'You do not have permission to approve or reject agent actions.'
            );

            return new JsonResponse($forbidden->body, $forbidden->httpStatus);
        }

        /** @var AgentActionCall|null $call */
        $call = $callRepository->find($id);
        $authorizedIds = bouncer()->getAuthorizedUserIds();
        $inScope = $call instanceof AgentActionCall
            && ($authorizedIds === null || ($call->user_id !== null && in_array((int) $call->user_id, array_map('intval', $authorizedIds), true)));

        if (! $inScope) {
            $notFound = ActionOutcome::error(404, 'not_found', 'No pending call with this id exists in your scope.');

            return new JsonResponse($notFound->body, $notFound->httpStatus);
        }

        $approveRaw = $request->input('approve');
        $approve = is_bool($approveRaw)
            ? $approveRaw
            : filter_var($approveRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($approve === null) {
            $invalid = ActionOutcome::error(422, 'invalid_params', 'Boolean [approve] is required.');

            return new JsonResponse($invalid->body, $invalid->httpStatus);
        }

        $editedParamsInput = $request->input('edited_params');
        $editedParams = is_array($editedParamsInput) ? $editedParamsInput : null;

        $feedbackNoteInput = $request->input('feedback_note');
        $feedbackNote = is_string($feedbackNoteInput) && trim($feedbackNoteInput) !== ''
            ? trim($feedbackNoteInput)
            : null;

        $distillRule = $request->boolean('distill_rule', false);

        $assertionInput = $request->input('webauthn_assertion') ?? $request->input('assertion');
        $signerProof = null;

        if ($approve && $assertionInput === null && UserPasskey::query()->where('user_id', $user->id)->exists()) {
            $required = ActionOutcome::error(
                422,
                'passkey_required',
                'A registered hardware Passkey signature is required to approve actions.'
            );

            return new JsonResponse($required->body, $required->httpStatus);
        }

        if ($assertionInput !== null) {
            if (! is_array($assertionInput)) {
                $invalidAssertion = ActionOutcome::error(422, 'invalid_assertion', 'WebAuthn assertion must be an object.');

                return new JsonResponse($invalidAssertion->body, $invalidAssertion->httpStatus);
            }

            try {
                $signerProof = $verifier->verifyAssertion(
                    $user->id,
                    $assertionInput,
                    $request->getSchemeAndHttpHost(),
                    $request->getHost(),
                    $call->id
                );
            } catch (TmaAuthException $exception) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => [
                        'code' => $exception->errorCode,
                        'message' => $exception->getMessage(),
                    ],
                ], 422);
            }
        }

        $outcome = $executor->decide(
            $call,
            $approve,
            $editedParams,
            $feedbackNote,
            $signerProof,
            $distillRule
        );

        $responseBody = $outcome->body;

        if ($signerProof !== null) {
            $responseBody['signer_proof'] = $signerProof;
        }

        return new JsonResponse($responseBody, $outcome->httpStatus);
    }
}
