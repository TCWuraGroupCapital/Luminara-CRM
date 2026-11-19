<?php

namespace Webkul\Agent\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationInstance;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Exceptions\AgentAccessDeniedException;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Agent\Repositories\AgentActionCallRepository;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\User\Models\User;

/**
 * The single path every agent call takes, whichever transport it arrived on:
 * find, authorize, enforce passport, validate, dry-run or deduplicate, gate on approval, run, record.
 */
final class ActionExecutor
{
    public const APPROVAL_PERMISSION = 'settings.other_settings.agent_approvals';

    /**
     * Far longer than any request may run, so a call still `running` after it was cut off mid-run.
     */
    private const INTERRUPTED_AFTER_MINUTES = 5;

    public function __construct(
        private ActionRegistry $registry,
        private AgentActionCallRepository $callRepository,
        private PassportGuard $passportGuard,
        private PlaybookMemory $playbook,
        private AgentWebhookDispatcher $webhooks,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(string $name, array $params, ?string $idempotencyKey = null, bool $dryRun = false): ActionOutcome
    {
        $this->settleStale();

        if (array_key_exists('dry_run', $params)) {
            if (! is_bool($params['dry_run'])) {
                return ActionOutcome::error(422, 'invalid_params', 'The parameters are not valid.', [
                    'Fix the fields listed in details and call again.',
                ], ['dry_run' => ['The dry_run field must be true or false.']]);
            }

            $dryRun = $dryRun || $params['dry_run'];
            unset($params['dry_run']);
        }

        $action = $this->registry->find($name);

        if (! $action) {
            $suggestion = $this->registry->suggest($name);

            return ActionOutcome::error(404, 'unknown_action', "There is no action named [{$name}].", array_filter([
                $suggestion ? "Did you mean [{$suggestion}]?" : null,
                'Read GET /api/agent/context for the actions you can call.',
            ]));
        }

        if (! bouncer()->hasPermission($action->permission())) {
            return ActionOutcome::error(403, 'forbidden', "Your role lacks the [{$action->permission()}] permission.", [
                'Do not retry. Ask an administrator to grant the permission to your role.',
            ]);
        }

        /** @var User $actor */
        $actor = auth()->guard('user')->user();
        $passport = $this->passportGuard->resolve($actor);

        if ($passport !== null && ($denied = $this->passportGuard->check($passport, $action)) !== null) {
            return $denied;
        }

        $validator = $this->makeValidator($params, $action);

        if ($validator->fails()) {
            return ActionOutcome::error(422, 'invalid_params', 'The parameters are not valid.', [
                'Fix the fields listed in details and call again.',
            ], $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        /**
         * The reason is the agent's own account of the call, not part of it: the same call retried
         * with different wording is still the same call.
         */
        $reason = Arr::pull($validated, 'reason');
        $needsApproval = $this->passportGuard->requiresApproval($passport, $action, $validated);

        if ($dryRun) {
            if ($passport !== null) {
                $this->passportGuard->recordDryRun($passport);
            }

            SecurityAuditRecorder::record('agent.action.dry_run', 'agent_action_call', null, [
                'action' => $name,
                'would_require_approval' => $needsApproval,
                'agent_user_id' => $actor->id,
                'agent_passport_id' => $passport?->id,
            ]);

            try {
                $subject = $needsApproval ? self::plain($action->subject($validated)) : [];

                DB::beginTransaction();
                $realDispatcher = Event::getFacadeRoot();
                Event::swap(new Dispatcher(app()));

                try {
                    $preview = ActionCallContext::using(null, fn () => $action->handle($validated));
                } finally {
                    Event::swap($realDispatcher);
                    DB::rollBack();
                }
            } catch (AgentAccessDeniedException $exception) {
                return ActionOutcome::error(403, 'forbidden', $exception->getMessage(), [
                    'Do not retry. Ask an administrator to grant the permission to your role.',
                ]);
            } catch (ModelNotFoundException $exception) {
                return ActionOutcome::notFound(class_basename($exception->getModel()));
            }

            return ActionOutcome::dryRun($name, $needsApproval, $subject, $preview);
        }

        /**
         * A person is never asked to approve a call against records that cannot be read.
         */
        try {
            $subject = $needsApproval ? self::plain($action->subject($validated)) : null;
        } catch (ModelNotFoundException $exception) {
            return ActionOutcome::notFound(class_basename($exception->getModel()));
        }

        $hash = hash('sha256', $name.'|'.json_encode($validated));

        try {
            /** @var AgentActionCall $call */
            $call = $this->callRepository->create([
                'user_id' => $actor->id,
                'actor_email_snapshot' => $actor->getAttribute('email'),
                /**
                 * Null when the call came from the in-app assistant on the user's own session, where
                 * Sanctum's docblock is wrong: there is no access token at all.
                 */
                // @phpstan-ignore instanceof.alwaysTrue
                'token_id' => $actor->currentAccessToken() instanceof PersonalAccessToken
                    ? $actor->currentAccessToken()->getKey()
                    : null,
                'agent_passport_id' => $passport?->id,
                'action' => $name,
                'params' => $validated,
                'reason' => $reason,
                'subject' => $subject,
                'payload_hash' => $hash,
                'idempotency_key' => $idempotencyKey,
                'status' => $needsApproval ? AgentActionCall::PENDING_APPROVAL : AgentActionCall::RUNNING,
            ]);
        } catch (UniqueConstraintViolationException) {
            /** @var AgentActionCall $earlier */
            $earlier = $this->callRepository->findOneWhere([
                'user_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $earlier->payload_hash === $hash
                ? ActionOutcome::fromCall($earlier)
                : ActionOutcome::error(409, 'idempotency_conflict', 'This Idempotency-Key was already used for a different call.', [
                    'Use a new Idempotency-Key for a new call.',
                ]);
        }

        if ($call->status === AgentActionCall::RUNNING) {
            $this->run($action, $call, $validated);
        }

        $this->audit($call);

        if ($call->status !== AgentActionCall::PENDING_APPROVAL) {
            Event::dispatch('agent.call.settled', [$call->refresh(), null]);
        }

        return ActionOutcome::fromCall($call->refresh());
    }

    /**
     * A human approves (optionally editing parameters) or rejects a waiting call.
     *
     * @param  array<string, mixed>|null  $editedParams
     * @param  array<string, mixed>|null  $signerProof
     */
    public function decide(
        AgentActionCall $call,
        bool $approve,
        ?array $editedParams = null,
        ?string $feedbackNote = null,
        ?array $signerProof = null,
        bool $distillRule = false,
    ): ActionOutcome {
        $this->settleStale();

        /** @var User $approver */
        $approver = auth()->guard('user')->user();
        $action = $this->registry->find($call->action);

        if (! $action || ! bouncer()->hasPermission($action->permission())) {
            return ActionOutcome::error(403, 'forbidden', 'You cannot decide a call for an action you may not perform yourself.');
        }

        $authorizedIds = bouncer()->getAuthorizedUserIds();

        if ($authorizedIds !== null && ($call->user_id === null || ! in_array((int) $call->user_id, $authorizedIds, true))) {
            return ActionOutcome::error(403, 'forbidden', 'You cannot decide a call outside your authorized team scope.');
        }

        $validatedEdited = null;

        if ($approve && $editedParams !== null) {
            $validator = $this->makeValidator($editedParams, $action);

            if ($validator->fails()) {
                return ActionOutcome::error(422, 'invalid_params', 'The edited parameters are not valid.', [
                    'Fix the edited fields listed in details and approve again.',
                ], $validator->errors()->toArray());
            }

            $validatedEdited = $validator->validated();
            Arr::pull($validatedEdited, 'reason');

            try {
                $action->subject($validatedEdited);
            } catch (AgentAccessDeniedException $exception) {
                return ActionOutcome::error(403, 'forbidden', $exception->getMessage());
            } catch (ModelNotFoundException) {
                return ActionOutcome::error(403, 'forbidden', 'The edited parameters reference a record outside your authorized scope.');
            }
        }

        $cleanFeedback = $feedbackNote !== null && trim($feedbackNote) !== ''
            ? mb_substr(trim($feedbackNote), 0, 500)
            : null;

        /**
         * The status condition makes the decision atomic: of two people clicking at once, exactly
         * one update matches, so a call can never run twice.
         */
        $claimed = AgentActionCall::query()
            ->whereKey($call->id)
            ->where('status', AgentActionCall::PENDING_APPROVAL)
            ->update([
                'status' => $approve ? AgentActionCall::RUNNING : AgentActionCall::REJECTED,
                'edited_params' => $approve && $validatedEdited !== null ? json_encode($validatedEdited) : null,
                'human_feedback' => $cleanFeedback,
                'signer_proof' => $signerProof !== null ? json_encode($signerProof) : null,
                'decided_by' => $approver->id,
                'decided_at' => now(),
            ]);

        if (! $claimed) {
            return ActionOutcome::error(409, 'already_decided', 'This call was already decided.');
        }

        $call->refresh();
        $canDistillPlaybook = $distillRule && bouncer()->hasPermission('agent_briefing');

        if ($approve) {
            $actor = $call->getRelationValue('user');

            if (! $actor instanceof User) {
                $call->update(['status' => AgentActionCall::FAILED, 'error_code' => 'forbidden']);
            } else {
                auth()->guard('user')->setUser($actor);

                try {
                    $this->run($action, $call, $validatedEdited ?? $call->params);
                } finally {
                    auth()->guard('user')->setUser($approver);
                }
            }

            if ($call->status === AgentActionCall::COMPLETED) {
                if ($validatedEdited !== null) {
                    $this->playbook->recordEditCorrection($call, $call->params, $validatedEdited, $cleanFeedback, $canDistillPlaybook);
                } elseif ($distillRule && $cleanFeedback !== null) {
                    $this->playbook->recordEditCorrection($call, $call->params, $call->params, $cleanFeedback, $canDistillPlaybook);
                }

                $passport = $call->getRelationValue('passport');

                if ($passport instanceof AgentPassport) {
                    $passport->increment('approved_streak');
                }
            }
        } else {
            if ($cleanFeedback !== null) {
                $this->playbook->recordRejectionFeedback($call, $cleanFeedback, $canDistillPlaybook);
            }

            $passport = $call->getRelationValue('passport');

            if ($passport instanceof AgentPassport) {
                $passport->update([
                    'approved_streak' => 0,
                    'rejected_count' => $passport->rejected_count + 1,
                ]);
            }
        }

        $this->audit($call);

        Event::dispatch('agent.call.settled', [$call->refresh(), $signerProof]);
        $call->refresh();

        $this->webhooks->dispatchDecision($call);

        return ActionOutcome::fromCall($call);
    }

    public function markReviewed(AgentActionCall $call): AgentActionCall
    {
        if ($call->reviewed_at === null) {
            $call->update(['reviewed_at' => now()]);
        }

        $this->audit($call);

        return $call->refresh();
    }

    /**
     * Closes calls nothing will ever finish: one cut off mid-run, and one no person decided in time.
     * Runs before a call is made, decided or read, so nobody acts on a status that is no longer true.
     */
    public function settleStale(): void
    {
        $stale = AgentActionCall::query()
            ->where(fn ($query) => $query
                ->where('status', AgentActionCall::RUNNING)
                ->where('updated_at', '<', now()->subMinutes(self::INTERRUPTED_AFTER_MINUTES)))
            ->orWhere(fn ($query) => $query
                ->where('status', AgentActionCall::PENDING_APPROVAL)
                ->where('created_at', '<', now()->subHours((int) config('agent.approval_ttl_hours'))))
            ->get();

        foreach ($stale as $call) {
            $claimed = AgentActionCall::query()->whereKey($call->id)->where('status', $call->status)->update([
                'status' => AgentActionCall::FAILED,
                'error_code' => $call->status === AgentActionCall::RUNNING ? 'interrupted' : 'expired',
            ]);

            if ($claimed) {
                $this->audit($call->refresh());
            }
        }
    }

    /**
     * An unknown parameter is refused rather than dropped, so an agent that misnames a field
     * finds out instead of believing it took effect.
     *
     * @param  array<string, mixed>  $params
     */
    private function makeValidator(array $params, AgentAction $action): ValidationInstance
    {
        $inputRules = $action->inputRules();
        $validator = Validator::make($params, $inputRules);

        $topAllowed = [];
        $nestedAllowed = [];

        foreach (array_keys($inputRules) as $ruleKey) {
            if (preg_match('/^([^\.]+)\.\*\.([^\.]+)$/', $ruleKey, $matches)) {
                $topAllowed[$matches[1]] = true;
                $nestedAllowed[$matches[1]][$matches[2]] = true;
            } elseif (str_ends_with($ruleKey, '.*')) {
                $topAllowed[substr($ruleKey, 0, -2)] = true;
            } else {
                $topAllowed[$ruleKey] = true;
            }
        }

        $validator->after(function (ValidationInstance $validator) use ($action, $params, $topAllowed, $nestedAllowed) {
            foreach (array_diff_key($params, $topAllowed) as $field => $value) {
                $validator->errors()->add((string) $field, 'This parameter is not accepted by the action.');
            }

            foreach ($nestedAllowed as $arrayField => $allowedSubKeys) {
                if (! isset($params[$arrayField]) || ! is_array($params[$arrayField])) {
                    continue;
                }

                foreach ($params[$arrayField] as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    foreach (array_diff_key($item, $allowedSubKeys) as $subKey => $unused) {
                        $validator->errors()->add("{$arrayField}.{$index}.{$subKey}", 'This parameter is not accepted by the action.');
                    }
                }
            }

            if ($validator->errors()->isEmpty()) {
                $action->afterValidate($validator, $params);
            }
        });

        return $validator;
    }

    /**
     * Runs as whoever is on the `user` guard, and checks the permission itself: between the request
     * and a later approval the caller's role may have lost it.
     *
     * @param  array<string, mixed>  $executionParams
     */
    private function run(AgentAction $action, AgentActionCall $call, array $executionParams): void
    {
        if (! bouncer()->hasPermission($action->permission())) {
            $call->update(['status' => AgentActionCall::FAILED, 'error_code' => 'forbidden']);

            return;
        }

        try {
            $freshSubject = null;

            /**
             * The approver agreed to the records as they were shown. If those underlying DB records
             * read differently now (and the human did not intentionally edit parameters), abort.
             */
            if ($call->edited_params !== null) {
                $freshSubject = self::plain($action->subject($executionParams));

                if ($call->subject !== null && self::targetSnapshotsDrifted($call->subject, $freshSubject)) {
                    $call->update(['status' => AgentActionCall::FAILED, 'error_code' => 'changed_since_request']);

                    return;
                }
            } elseif ($call->subject !== null && self::plain($action->subject($call->params)) != $call->subject) {
                $call->update(['status' => AgentActionCall::FAILED, 'error_code' => 'changed_since_request']);

                return;
            }

            /**
             * The outcome commits with the action's own writes, so a call cut off mid-run is left
             * `running` with nothing changed and can be reported as such.
             */
            DB::transaction(fn () => $call->update(array_filter([
                'status' => AgentActionCall::COMPLETED,
                'subject' => $freshSubject,
                'result' => ActionCallContext::using($call, fn () => $action->handle($executionParams)),
            ], fn ($v) => $v !== null)));
        } catch (AgentAccessDeniedException) {
            $call->update(['status' => AgentActionCall::FAILED, 'error_code' => 'forbidden', 'result' => null]);
        } catch (ModelNotFoundException $exception) {
            $call->update([
                'status' => AgentActionCall::FAILED,
                'error_code' => 'not_found',
                'result' => ['missing' => class_basename($exception->getModel())],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $call->update(['status' => AgentActionCall::FAILED, 'error_code' => 'action_failed', 'result' => null]);
        }
    }

    /**
     * The value as it reads back from a JSON column, so a fresh subject compares equal to a stored one.
     *
     * @param  array<int, array<string, mixed>>  $subject
     * @return array<int, array<string, mixed>>
     */
    private static function plain(array $subject): array
    {
        return json_decode((string) json_encode($subject), true);
    }

    /**
     * Detect if any target record still present after human parameter edits changed in the database
     * since the approval slip was created (ignoring intentional destination changes like `moves_to`).
     *
     * @param  array<int, array<string, mixed>>  $originalSubject
     * @param  array<int, array<string, mixed>>  $freshSubject
     */
    private static function targetSnapshotsDrifted(array $originalSubject, array $freshSubject): bool
    {
        $indexedOriginal = [];

        foreach ($originalSubject as $row) {
            $id = $row['id'] ?? ($row['slot'] ?? null);

            if ($id !== null && $id !== '') {
                $indexedOriginal[(string) ($row['entity'] ?? 'lead').':'.(string) $id] = Arr::except($row, ['moves_to']);
            }
        }

        foreach ($freshSubject as $row) {
            $id = $row['id'] ?? ($row['slot'] ?? null);

            if ($id === null || $id === '') {
                continue;
            }

            $key = (string) ($row['entity'] ?? 'lead').':'.(string) $id;

            if (isset($indexedOriginal[$key]) && Arr::except($row, ['moves_to']) != $indexedOriginal[$key]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parameters and the reason stay out of the security audit log; they may hold personal data and
     * are already on the call record.
     */
    private function audit(AgentActionCall $call): void
    {
        SecurityAuditRecorder::record('agent.action.'.$call->status, 'agent_action_call', $call->id, [
            'action' => $call->action,
            'error_code' => $call->error_code,
            'agent_user_id' => $call->user_id,
            'agent_token_id' => $call->token_id,
            'agent_passport_id' => $call->agent_passport_id,
            'decided_by' => $call->decided_by,
            'reviewed_at' => $call->reviewed_at?->toIso8601String(),
        ]);
    }
}
