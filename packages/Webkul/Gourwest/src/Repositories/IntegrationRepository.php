<?php

namespace Webkul\Gourwest\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Gourwest\Contracts\Integration as IntegrationContract;

class IntegrationRepository extends Repository
{
    public function model(): string
    {
        return IntegrationContract::class;
    }
}
