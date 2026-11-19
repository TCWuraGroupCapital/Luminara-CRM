<?php

namespace Webkul\Gourwest\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Webkul\Core\Eloquent\Repository;
use Webkul\Gourwest\Contracts\CustomerProfile as CustomerProfileContract;
use Webkul\Gourwest\Models\CustomerProfile;

class CustomerProfileRepository extends Repository
{
    public function model(): string
    {
        return CustomerProfileContract::class;
    }

    /**
     * Build a Two-Gate data-scoped query restricted to the caller's authorized user IDs.
     *
     * @param  array<int, int>|null  $authorizedUserIds
     * @return Builder<CustomerProfile>
     */
    public function scopedQuery(?array $authorizedUserIds = null): Builder
    {
        /** @var Builder<CustomerProfile> $query */
        $query = CustomerProfile::query()->with(['person', 'owner']);

        if ($authorizedUserIds !== null) {
            $query->where(function (Builder $subQuery) use ($authorizedUserIds): void {
                $subQuery->whereIn('gourwest_customer_profiles.owner_user_id', $authorizedUserIds)
                    ->orWhereHas('person', function (Builder $personQuery) use ($authorizedUserIds): void {
                        $personQuery->whereIn('persons.user_id', $authorizedUserIds);
                    });
            });
        }

        return $query;
    }

    /**
     * Resolve all user IDs owning a customer profile (profile owner + linked person owner).
     *
     * @return list<int>
     */
    public function resolveOwnerIds(CustomerProfile $profile): array
    {
        $profile->loadMissing('person');

        $ids = [];

        if ($profile->owner_user_id !== null) {
            $ids[] = (int) $profile->owner_user_id;
        }

        $personUserId = $profile->person?->getAttribute('user_id');

        if ($personUserId !== null) {
            $ids[] = (int) $personUserId;
        }

        return array_values(array_unique($ids));
    }
}
