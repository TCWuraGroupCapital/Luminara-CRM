<?php

namespace Webkul\Gourwest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Gourwest\Contracts\ServiceTicket as ServiceTicketContract;
use Webkul\User\Models\User;
use Webkul\User\Models\UserProxy;

/**
 * @property int $id
 * @property string $ticket_number
 * @property int|null $person_id
 * @property int|null $user_id
 * @property string $region_code
 * @property string $channel
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property string $subject
 * @property string|null $summary
 * @property Carbon|null $sla_due_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Person|null $person
 * @property-read User|null $owner
 */
class ServiceTicket extends Model implements ServiceTicketContract
{
    protected $table = 'gourwest_service_tickets';

    protected $fillable = [
        'ticket_number',
        'person_id',
        'user_id',
        'region_code',
        'channel',
        'category',
        'priority',
        'status',
        'subject',
        'summary',
        'sla_due_at',
        'resolved_at',
    ];

    protected $casts = [
        'person_id' => 'integer',
        'user_id' => 'integer',
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonProxy::modelClass(), 'person_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }
}
