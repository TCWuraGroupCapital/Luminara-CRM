<?php

namespace Webkul\Agent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Webkul\Agent\Contracts\AgentActionCall as AgentActionCallContract;
use Webkul\User\Models\User;
use Webkul\User\Models\UserProxy;

/**
 * One call an agent made: the receipt for retries, the approval request, and the run history.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $actor_email_snapshot
 * @property int|null $token_id
 * @property int|null $agent_passport_id
 * @property string $action
 * @property array<string, mixed> $params
 * @property array<string, mixed>|null $edited_params
 * @property string|null $reason Why the agent says it made the call. Its own claim, never verified.
 * @property string|null $human_feedback
 * @property array<int, array<string, mixed>>|null $subject The records a waiting call will change, as they were when it was made.
 * @property string $payload_hash
 * @property string|null $idempotency_key
 * @property string $status
 * @property array<string, mixed>|null $result
 * @property string|null $error_code
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property array<string, mixed>|null $signer_proof
 * @property Carbon|null $reviewed_at
 * @property string|null $block_hash
 * @property string|null $receipt_code
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $user
 * @property-read AgentPassport|null $passport
 */
class AgentActionCall extends Model implements AgentActionCallContract
{
    public const RUNNING = 'running';

    public const PENDING_APPROVAL = 'pending_approval';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const REJECTED = 'rejected';

    protected $table = 'agent_action_calls';

    protected $fillable = [
        'user_id',
        'actor_email_snapshot',
        'token_id',
        'agent_passport_id',
        'action',
        'params',
        'edited_params',
        'reason',
        'human_feedback',
        'subject',
        'payload_hash',
        'idempotency_key',
        'status',
        'result',
        'error_code',
        'decided_by',
        'decided_at',
        'signer_proof',
        'reviewed_at',
        'block_hash',
        'receipt_code',
    ];

    protected $casts = [
        'params' => 'array',
        'edited_params' => 'array',
        'subject' => 'array',
        'result' => 'array',
        'signer_proof' => 'array',
        'decided_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Agent action call receipts are append-only and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    public function passport(): BelongsTo
    {
        return $this->belongsTo(AgentPassportProxy::modelClass(), 'agent_passport_id');
    }
}
