<?php

namespace Webkul\Agent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Webkul\Agent\Contracts\AgentPreferenceSignal as AgentPreferenceSignalContract;

/**
 * Human edit-to-sign or rejection feedback captured as a preference signal.
 *
 * @property int $id
 * @property int|null $call_id
 * @property int|null $user_id
 * @property string $action
 * @property string $signal_type
 * @property array<string, mixed>|null $param_diff
 * @property string|null $feedback_note
 * @property string|null $distilled_rule
 * @property Carbon $created_at
 */
class AgentPreferenceSignal extends Model implements AgentPreferenceSignalContract
{
    public $timestamps = false;

    protected $table = 'agent_preference_signals';

    protected $fillable = [
        'call_id',
        'user_id',
        'action',
        'signal_type',
        'param_diff',
        'feedback_note',
        'distilled_rule',
        'created_at',
    ];

    protected $casts = [
        'call_id' => 'integer',
        'user_id' => 'integer',
        'param_diff' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $signal): void {
            $signal->created_at ??= now();
        });
    }
}
