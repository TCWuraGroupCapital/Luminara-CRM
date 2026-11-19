<?php

namespace Webkul\Tma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Webkul\Tma\Contracts\UserPasskey as UserPasskeyContract;
use Webkul\User\Models\User;
use Webkul\User\Models\UserProxy;

/**
 * @property int $id
 * @property int $user_id
 * @property string $credential_id
 * @property string $label
 * @property string $alg
 * @property string $public_key
 * @property int $sign_count
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class UserPasskey extends Model implements UserPasskeyContract
{
    public const ALG_ES256 = 'es256';

    public const ALG_ED25519 = 'ed25519';

    protected $table = 'user_passkeys';

    protected $fillable = [
        'user_id',
        'credential_id',
        'label',
        'alg',
        'public_key',
        'sign_count',
        'last_used_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'sign_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }
}
