<?php

namespace Webkul\Security\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Security\Contracts\SecurityAuditLog;

class SecurityAuditLogRepository extends Repository
{
    public function model(): string
    {
        return SecurityAuditLog::class;
    }
}
