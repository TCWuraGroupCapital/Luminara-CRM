<?php

namespace Webkul\Agent\Repositories;

use Webkul\Agent\Contracts\AgentActionCall;
use Webkul\Core\Eloquent\Repository;

class AgentActionCallRepository extends Repository
{
    public function model(): string
    {
        return AgentActionCall::class;
    }
}
