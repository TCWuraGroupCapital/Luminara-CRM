<?php

namespace Webkul\Agent\Support;

use Webkul\Agent\Models\AgentActionCall;

/**
 * What a call produced, in one envelope every transport returns unchanged.
 */
final readonly class ActionOutcome
{
    /**
     * @param  array<string, mixed>  $body
     */
    private function __construct(public int $httpStatus, public array $body) {}

    /**
     * @param  array<int, string>  $nextSteps  What the agent should do instead, in plain words.
     * @param  array<string, mixed>  $details
     */
    public static function error(int $httpStatus, string $code, string $message, array $nextSteps = [], array $details = []): self
    {
        return new self($httpStatus, [
            'ok' => false,
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'next_steps' => $nextSteps,
                'details' => $details,
            ]),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $subject
     * @param  array<string, mixed>  $preview
     */
    public static function dryRun(string $action, bool $needsApproval, array $subject, array $preview): self
    {
        return new self(200, [
            'ok' => true,
            'dry_run' => true,
            'action' => $action,
            'would_require_approval' => $needsApproval,
            'subject' => $subject,
            'preview' => $preview,
        ]);
    }

    public static function fromCall(AgentActionCall $call): self
    {
        $base = array_filter([
            'call_id' => $call->id,
            'action' => $call->action,
            'status' => $call->status,
            'receipt_code' => $call->receipt_code,
            'block_hash' => $call->block_hash,
            'human_feedback' => $call->human_feedback,
        ], fn ($value) => $value !== null);

        return match ($call->status) {
            AgentActionCall::COMPLETED => new self(200, ['ok' => true] + $base + ['result' => $call->result]),
            AgentActionCall::PENDING_APPROVAL => new self(202, ['ok' => true] + $base + [
                'next_steps' => [
                    'A human must approve this call in the CRM under Settings > Agent Approvals. It expires in '.config('agent.approval_ttl_hours').' hours and will not run if the records change first.',
                    "Read GET /api/agent/calls/{$call->id} later for the outcome. Do not repeat the call.",
                ],
            ]),
            AgentActionCall::RUNNING => self::error(409, 'in_progress', 'This call is still running.', [
                "Read GET /api/agent/calls/{$call->id} for the outcome.",
            ], $base),
            AgentActionCall::REJECTED => self::error(403, 'rejected', 'A human rejected this call.', [
                'Do not retry. Tell the person you are working for that it was rejected.',
            ], $base),
            default => match ($call->error_code) {
                'not_found' => self::notFound($call->result['missing'] ?? '', $base),
                'forbidden' => self::error(403, 'forbidden', 'The role this call runs as no longer holds the permission.', [], $base),
                'changed_since_request' => self::error(409, 'changed_since_request', 'The records changed after this call was made, so the approval no longer covered it. Nothing was changed.', [
                    'Read the records again and make a new call if it is still wanted.',
                ], $base),
                'expired' => self::error(410, 'expired', 'No one decided this call in time. Nothing was changed.', [
                    'Make a new call if it is still wanted.',
                ], $base),
                'interrupted' => self::error(500, 'interrupted', 'This call was cut off before it finished. No CRM record was changed.', [
                    'Make the call again with a new Idempotency-Key if it is still wanted.',
                ], $base),
                default => self::error(500, 'action_failed', 'The action failed and changed nothing.', [], $base),
            },
        };
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function notFound(string $missing, array $details = []): self
    {
        return self::error(404, 'not_found', 'A record this action needs does not exist or is outside your data scope.', [
            'Check the id you sent. details.missing names the kind of record that was not found.',
        ], $details + ['missing' => $missing]);
    }
}
