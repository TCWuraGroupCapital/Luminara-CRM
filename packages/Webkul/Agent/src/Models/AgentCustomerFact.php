<?php

namespace Webkul\Agent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Webkul\Agent\Contracts\AgentCustomerFact as AgentCustomerFactContract;

/**
 * One short, human-approved fact about a person or organisation, kept for agents to recall.
 *
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $category
 * @property string $fact
 * @property string $visibility
 * @property string $status
 * @property Carbon|null $valid_until
 * @property int|null $owner_user_id
 * @property int|null $approved_by_user_id
 * @property int|null $source_call_id
 * @property int|null $superseded_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AgentCustomerFact extends Model implements AgentCustomerFactContract
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_RETRACTED = 'retracted';

    public const VISIBILITY_TEAM = 'team';

    public const VISIBILITY_PRIVATE = 'private';

    protected $table = 'agent_customer_facts';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'category',
        'fact',
        'visibility',
        'status',
        'valid_until',
        'owner_user_id',
        'approved_by_user_id',
        'source_call_id',
        'superseded_by_id',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'valid_until' => 'date',
        'owner_user_id' => 'integer',
        'approved_by_user_id' => 'integer',
        'source_call_id' => 'integer',
        'superseded_by_id' => 'integer',
    ];
}
