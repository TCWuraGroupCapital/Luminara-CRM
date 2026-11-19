<?php

namespace Webkul\Tma\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Tma\Contracts\UserPasskey;

class UserPasskeyRepository extends Repository
{
    public function model(): string
    {
        return UserPasskey::class;
    }
}
