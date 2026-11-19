<?php

namespace Webkul\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Webkul\Security\Contracts\UserMfaSetting as UserMfaSettingContract;
use Webkul\User\Models\UserProxy;

/**
 * @property int $id
 * @property int $user_id
 * @property string $secret_encrypted
 * @property array<int, string>|null $recovery_codes_hashes
 * @property bool $mfa_enabled
 * @property int|null $last_used_slice
 * @property Carbon|null $enabled_at
 */
class UserMfaSetting extends Model implements UserMfaSettingContract
{
    protected $table = 'user_mfa_settings';

    protected $fillable = [
        'user_id',
        'secret_encrypted',
        'recovery_codes_hashes',
        'mfa_enabled',
        'last_used_slice',
        'enabled_at',
    ];

    protected $hidden = [
        'secret_encrypted',
        'recovery_codes_hashes',
    ];

    protected $casts = [
        'recovery_codes_hashes' => 'array',
        'mfa_enabled' => 'boolean',
        'last_used_slice' => 'integer',
        'enabled_at' => 'datetime',
    ];

    public function setPlainSecret(string $base32Secret): void
    {
        $this->secret_encrypted = Crypt::encryptString($base32Secret);
    }

    public function decryptedSecret(): string
    {
        return Crypt::decryptString($this->secret_encrypted);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }
}
