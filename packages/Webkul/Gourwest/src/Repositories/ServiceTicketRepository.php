<?php

namespace Webkul\Gourwest\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Webkul\Core\Eloquent\Repository;
use Webkul\Gourwest\Contracts\ServiceTicket as ServiceTicketContract;
use Webkul\Gourwest\Models\ServiceTicket;

class ServiceTicketRepository extends Repository
{
    public function model(): string
    {
        return ServiceTicketContract::class;
    }

    /**
     * Build a Two-Gate data-scoped query restricted to the caller's authorized user IDs.
     *
     * @param  array<int, int>|null  $authorizedUserIds
     * @return Builder<ServiceTicket>
     */
    public function scopedQuery(?array $authorizedUserIds = null): Builder
    {
        /** @var Builder<ServiceTicket> $query */
        $query = ServiceTicket::query()->with(['person', 'owner']);

        if ($authorizedUserIds !== null) {
            $query->whereIn('gourwest_service_tickets.user_id', $authorizedUserIds);
        }

        return $query;
    }
}
