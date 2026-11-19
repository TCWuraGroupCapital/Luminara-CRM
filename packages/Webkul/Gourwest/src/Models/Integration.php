<?php

namespace Webkul\Gourwest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Webkul\Gourwest\Contracts\Integration as IntegrationContract;

/**
 * @property int $id
 * @property string $system_code
 * @property string $name
 * @property string $vendor_owner
 * @property string $contract_version
 * @property string $sync_mode
 * @property string $status
 * @property int $sla_target_ms
 * @property int $last_latency_ms
 * @property int $records_synced_24h
 * @property int $failed_events_count
 * @property Carbon|null $last_heartbeat_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Integration extends Model implements IntegrationContract
{
    protected $table = 'gourwest_integrations';

    protected $fillable = [
        'system_code',
        'name',
        'vendor_owner',
        'contract_version',
        'sync_mode',
        'status',
        'sla_target_ms',
        'last_latency_ms',
        'records_synced_24h',
        'failed_events_count',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'sla_target_ms' => 'integer',
        'last_latency_ms' => 'integer',
        'records_synced_24h' => 'integer',
        'failed_events_count' => 'integer',
        'last_heartbeat_at' => 'datetime',
    ];

    public function syncEvents(): HasMany
    {
        return $this->hasMany(SyncEventProxy::modelClass(), 'integration_id');
    }
}
