<?php

namespace Webkul\Agent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Webkul\Agent\Contracts\AgentPassport as AgentPassportContract;
use Webkul\User\Models\User;
use Webkul\User\Models\UserProxy;

/**
 * Scoped agent identity bound to a user's role, daily call budget, and earned-trust policy.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $token_id
 * @property string $slug
 * @property string $name
 * @property array<int, string>|null $allowed_actions
 * @property int $daily_call_limit
 * @property float|null $auto_approve_value_ceiling
 * @property string $trust_mode
 * @property int $approved_streak
 * @property int $rejected_count
 * @property string|null $webhook_url
 * @property string|null $webhook_secret
 * @property string|null $agent_pubkey
 * @property bool $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $user
 */
class AgentPassport extends Model implements AgentPassportContract
{
    public const TRUST_STANDARD = 'standard';

    public const TRUST_ALWAYS_CONFIRM = 'always_confirm';

    public const TRUST_EARNED_AUTO = 'earned_auto';

    protected $table = 'agent_passports';

    protected $fillable = [
        'user_id',
        'token_id',
        'slug',
        'name',
        'allowed_actions',
        'daily_call_limit',
        'auto_approve_value_ceiling',
        'trust_mode',
        'approved_streak',
        'rejected_count',
        'webhook_url',
        'webhook_secret',
        'agent_pubkey',
        'status',
    ];

    protected $casts = [
        'allowed_actions' => 'array',
        'daily_call_limit' => 'integer',
        'auto_approve_value_ceiling' => 'float',
        'approved_streak' => 'integer',
        'rejected_count' => 'integer',
        'webhook_secret' => 'encrypted',
        'status' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    public function calls(): HasMany
    {
        return $this->hasMany(AgentActionCallProxy::modelClass(), 'agent_passport_id');
    }

    public function allowsAction(string $actionName): bool
    {
        if ($this->allowed_actions === null) {
            return true;
        }

        foreach ($this->allowed_actions as $pattern) {
            if (Str::is((string) $pattern, $actionName)) {
                return true;
            }
        }

        return false;
    }
}
