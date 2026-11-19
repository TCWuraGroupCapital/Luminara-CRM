<?php

namespace Webkul\Gourwest\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Gourwest\Models\CustomerProfile;
use Webkul\Gourwest\Models\Integration;
use Webkul\Gourwest\Models\ServiceTicket;
use Webkul\Gourwest\Models\SyncEvent;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected $models = [
        CustomerProfile::class,
        Integration::class,
        SyncEvent::class,
        ServiceTicket::class,
    ];
}
