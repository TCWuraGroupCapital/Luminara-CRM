<?php

namespace Webkul\Experiment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Webkul\Experiment\Contracts\Experiment as ExperimentContract;
use Webkul\User\Models\UserProxy;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property int $traffic_allocation
 * @property array<int, array{key: string, name: string, weight: int, payload?: array<string, mixed>|null}> $variants
 * @property string|null $winner_variant_key
 * @property string $salt
 * @property int|null $user_id
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Experiment extends Model implements ExperimentContract
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CONCLUDED = 'concluded';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'traffic_allocation',
        'variants',
        'winner_variant_key',
        'salt',
        'user_id',
        'started_at',
        'ended_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'traffic_allocation' => 'integer',
        'variants' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the events recorded for this experiment.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ExperimentEventProxy::modelClass(), 'experiment_id');
    }

    /**
     * Get the admin user who created the experiment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }

    /**
     * Return the baseline (first) variant key.
     */
    public function controlVariantKey(): string
    {
        $first = $this->variants[0] ?? null;

        return $first !== null ? (string) $first['key'] : 'control';
    }

    /**
     * Return the static variant served when the experiment is not actively bucketing traffic.
     */
    public function resolvedStaticVariantKey(): string
    {
        if (
            $this->status === self::STATUS_CONCLUDED
            && is_string($this->winner_variant_key)
            && $this->winner_variant_key !== ''
        ) {
            foreach ($this->variants as $variant) {
                if ($variant['key'] === $this->winner_variant_key) {
                    return $this->winner_variant_key;
                }
            }
        }

        return $this->controlVariantKey();
    }
}
