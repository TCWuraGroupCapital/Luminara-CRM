<?php

namespace Webkul\Workspace\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Webkul\Workspace\WorkspaceManager;

/**
 * One business. The row lives in the landlord database; everything the business owns lives
 * in the database named by `database`.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $database
 * @property string $status
 * @property string $owner_name
 * @property string $owner_email
 * @property string|null $owner_password
 * @property string $status_token
 * @property string|null $verification_token
 * @property Carbon|null $created_at
 * @property string|null $error
 */
class Workspace extends Model
{
    /**
     * Signed up, but the owner has not yet opened the link emailed to them. No database exists.
     */
    public const STATUS_UNVERIFIED = 'unverified';

    /**
     * How long an emailed confirmation link stays valid.
     */
    public const VERIFICATION_HOURS = 24;

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SUSPENDED = 'suspended';

    protected $connection = WorkspaceManager::LANDLORD;

    protected $table = 'workspaces';

    protected $fillable = [
        'name',
        'slug',
        'database',
        'status',
        'owner_name',
        'owner_email',
        'owner_password',
        'status_token',
        'verification_token',
        'error',
    ];

    protected $hidden = [
        'owner_password',
        'status_token',
        'verification_token',
    ];

    public static function hashVerificationToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @param  Builder<Workspace>  $query
     * @return Builder<Workspace>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }
}
