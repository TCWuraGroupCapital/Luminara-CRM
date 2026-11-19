<?php

namespace Webkul\Gourwest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Gourwest\Contracts\CustomerProfile as CustomerProfileContract;
use Webkul\User\Models\User;
use Webkul\User\Models\UserProxy;

/**
 * @property int $id
 * @property int $person_id
 * @property int|null $owner_user_id
 * @property string $golden_record_uid
 * @property string $region_code
 * @property string $home_outlet
 * @property string $privacy_regime
 * @property string $consent_status
 * @property string $loyalty_member_number
 * @property string $loyalty_tier
 * @property int $loyalty_points
 * @property string $lifetime_spend_aud
 * @property bool $is_primary_golden
 * @property int|null $merged_into_profile_id
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Person|null $person
 * @property-read User|null $owner
 */
class CustomerProfile extends Model implements CustomerProfileContract
{
    protected $table = 'gourwest_customer_profiles';

    protected $fillable = [
        'person_id',
        'owner_user_id',
        'golden_record_uid',
        'region_code',
        'home_outlet',
        'privacy_regime',
        'consent_status',
        'loyalty_member_number',
        'loyalty_tier',
        'loyalty_points',
        'lifetime_spend_aud',
        'is_primary_golden',
        'merged_into_profile_id',
        'last_synced_at',
    ];

    protected $casts = [
        'person_id' => 'integer',
        'owner_user_id' => 'integer',
        'loyalty_points' => 'integer',
        'lifetime_spend_aud' => 'decimal:2',
        'is_primary_golden' => 'boolean',
        'merged_into_profile_id' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonProxy::modelClass(), 'person_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'owner_user_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_profile_id');
    }

    public function mergedProfiles(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_profile_id');
    }
}
