<?php

namespace Webkul\Agent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Webkul\Agent\Contracts\AgentPlaybookBlock as AgentPlaybookBlockContract;

/**
 * Versioned business playbook memory block injected into agent contexts.
 *
 * @property int $id
 * @property string $slot
 * @property string $content
 * @property int $version
 * @property int|null $updated_by_user_id
 * @property int|null $source_call_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AgentPlaybookBlock extends Model implements AgentPlaybookBlockContract
{
    protected $table = 'agent_playbook_blocks';

    protected $fillable = [
        'slot',
        'content',
        'version',
        'updated_by_user_id',
        'source_call_id',
    ];

    protected $casts = [
        'version' => 'integer',
        'updated_by_user_id' => 'integer',
        'source_call_id' => 'integer',
    ];
}
