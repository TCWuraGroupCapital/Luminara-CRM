<?php

namespace Webkul\Security\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Security\Contracts\UserMfaSetting;

class UserMfaSettingRepository extends Repository
{
    public function model(): string
    {
        return UserMfaSetting::class;
    }
}
