<?php

namespace Webkul\Gourwest\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Gourwest\Contracts\SyncEvent as SyncEventContract;

class SyncEventRepository extends Repository
{
    public function model(): string
    {
        return SyncEventContract::class;
    }
}
