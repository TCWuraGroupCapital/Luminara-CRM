<?php

namespace Webkul\Security\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Security\Models\SecurityAuditLog;
use Webkul\Security\Models\UserMfaSetting;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected $models = [
        UserMfaSetting::class,
        SecurityAuditLog::class,
    ];
}
