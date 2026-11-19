<?php

namespace Webkul\Ledger\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;
use Webkul\Ledger\Contracts\LedgerEpoch as LedgerEpochContract;

/**
 * Immutable RFC 6962 binary Merkle Epoch sealing a contiguous slice of Data Blocks.
 *
 * @property int $id
 * @property int $start_height
 * @property int $end_height
 * @property int $block_count
 * @property string $merkle_root
 * @property string $prev_epoch_root
 * @property string $server_signature
 * @property string $anchor_driver
 * @property string|null $anchor_reference
 * @property Carbon $sealed_at
 * @property-read Collection<int, DataBlock> $blocks
 */
class LedgerEpoch extends Model implements LedgerEpochContract
{
    public const CREATED_AT = 'sealed_at';

    public const UPDATED_AT = null;

    protected $table = 'ledger_epochs';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'start_height',
        'end_height',
        'block_count',
        'merkle_root',
        'prev_epoch_root',
        'server_signature',
        'anchor_driver',
        'anchor_reference',
        'sealed_at',
    ];

    protected $casts = [
        'start_height' => 'integer',
        'end_height' => 'integer',
        'block_count' => 'integer',
        'sealed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Ledger Merkle Epochs are immutable and cannot be modified.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Ledger Merkle Epochs are append-only and cannot be deleted.');
        });
    }

    /**
     * @return HasMany<DataBlock, $this>
     */
    public function blocks(): HasMany
    {
        /** @var class-string<DataBlock> $modelClass */
        $modelClass = DataBlockProxy::modelClass();

        return $this->hasMany($modelClass, 'epoch_id')->orderBy('block_height');
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
}
