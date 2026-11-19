<?php

namespace Webkul\Tma\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Tma\Contracts\TelegramIdentity;

class TelegramIdentityRepository extends Repository
{
    public function model(): string
    {
        return TelegramIdentity::class;
    }
}
