<?php

namespace Webkul\Experiment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Webkul\Experiment\Contracts\ExperimentEvent as ExperimentEventContract;

/**
 * @property int $id
 * @property int $experiment_id
 * @property string $variant_key
 * @property string $subject_hash
 * @property string $event_type
 * @property string $event_key
 * @property float|string $value
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
class ExperimentEvent extends Model implements ExperimentEventContract
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'experiment_id',
        'variant_key',
        'subject_hash',
        'event_type',
        'event_key',
        'value',
        'metadata',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'experiment_id' => 'integer',
        'value' => 'float',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the parent experiment.
     */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(ExperimentProxy::modelClass(), 'experiment_id');
    }
}
