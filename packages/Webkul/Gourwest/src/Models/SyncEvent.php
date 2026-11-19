<?php

namespace Webkul\Gourwest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Webkul\Gourwest\Contracts\SyncEvent as SyncEventContract;

/**
 * @property int $id
 * @property int $integration_id
 * @property string $region_code
 * @property string $event_type
 * @property string $external_ref
 * @property string $payload_hash
 * @property string $status
 * @property int $latency_ms
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Integration $integration
 */
class SyncEvent extends Model implements SyncEventContract
{
    protected $table = 'gourwest_sync_events';

    protected $fillable = [
        'integration_id',
        'region_code',
        'event_type',
        'external_ref',
        'payload_hash',
        'status',
        'latency_ms',
        'error_message',
    ];

    protected $casts = [
        'integration_id' => 'integer',
        'latency_ms' => 'integer',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(IntegrationProxy::modelClass(), 'integration_id');
    }
}
