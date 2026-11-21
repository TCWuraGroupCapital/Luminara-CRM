<?php

namespace Webkul\Ledger\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Webkul\Ledger\Contracts\DataBlock as DataBlockContract;
use Webkul\Ledger\Support\CanonicalJson;
use Webkul\User\Models\User;
use Webkul\User\Models\UserProxy;

/**
 * Append-only RFC 8785 JCS canonical Data Block on the Luminara Ledger.
 *
 * @property int $id
 * @property int $block_height
 * @property string $receipt_code
 * @property string $prev_block_hash
 * @property string $block_hash
 * @property string $event_name
 * @property string $entity_type
 * @property string $entity_id
 * @property string $actor_kind
 * @property int|null $actor_id
 * @property int|null $agent_passport_id
 * @property array<array-key, mixed> $canonical_payload
 * @property string $signer_alg
 * @property string $signer_pubkey
 * @property string $signer_proof
 * @property int|null $epoch_id
 * @property Carbon|null $redacted_at
 * @property string|null $redaction_receipt
 * @property Carbon $created_at
 * @property-read LedgerEpoch|null $epoch
 */
class DataBlock extends Model implements DataBlockContract
{
    public const UPDATED_AT = null;

    protected $table = 'data_blocks';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'block_height',
        'receipt_code',
        'prev_block_hash',
        'block_hash',
        'event_name',
        'entity_type',
        'entity_id',
        'actor_kind',
        'actor_id',
        'agent_passport_id',
        'canonical_payload',
        'signer_alg',
        'signer_pubkey',
        'signer_proof',
        'epoch_id',
        'created_at',
    ];

    protected $casts = [
        'block_height' => 'integer',
        'actor_id' => 'integer',
        'agent_passport_id' => 'integer',
        'canonical_payload' => 'array',
        'epoch_id' => 'integer',
        'redacted_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function isRedacted(): bool
    {
        return $this->redacted_at !== null;
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Ledger Data Blocks are immutable and cannot be modified.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Ledger Data Blocks are append-only and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<LedgerEpoch, $this>
     */
    public function epoch(): BelongsTo
    {
        /** @var class-string<LedgerEpoch> $modelClass */
        $modelClass = LedgerEpochProxy::modelClass();

        return $this->belongsTo($modelClass, 'epoch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        /** @var class-string<User> $modelClass */
        $modelClass = UserProxy::modelClass();

        return $this->belongsTo($modelClass, 'actor_id');
    }

    /**
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }

        return $this->asDateTime($value)->utc()->format('Y-m-d H:i:s.u');
    }

    /**
     * @param  mixed  $value
     */
    protected function asDateTime($value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        if (is_string($value)) {
            return Carbon::parse($value, 'UTC')->utc();
        }

        return Carbon::instance(parent::asDateTime($value))->utc();
    }

    /**
     * @param  mixed  $value
     * @param  int  $flags
     * @return string
     */
    protected function asJson($value, $flags = 0)
    {
        return CanonicalJson::encode($value);
    }
}
