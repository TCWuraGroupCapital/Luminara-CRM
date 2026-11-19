<?php

namespace Webkul\Agent\Repositories;

use Webkul\Agent\Contracts\AgentPassport;
use Webkul\Core\Eloquent\Repository;

class AgentPassportRepository extends Repository
{
    public function model(): string
    {
        return AgentPassport::class;
    }
}
