<?php

namespace Webkul\Tma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Webkul\Tma\Contracts\TelegramIdentity as TelegramIdentityContract;
use Webkul\User\Models\User;
use Webkul\User\Models\UserProxy;

/**
 * @property int $id
 * @property int $user_id
 * @property int $telegram_user_id
 * @property string|null $telegram_username
 * @property string|null $first_name
 * @property int|null $chat_id
 * @property int|null $last_auth_date
 * @property Carbon $linked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class TelegramIdentity extends Model implements TelegramIdentityContract
{
    protected $table = 'telegram_identities';

    protected $fillable = [
        'user_id',
        'telegram_user_id',
        'telegram_username',
        'first_name',
        'chat_id',
        'last_auth_date',
        'linked_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'telegram_user_id' => 'integer',
        'chat_id' => 'integer',
        'last_auth_date' => 'integer',
        'linked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }
}
