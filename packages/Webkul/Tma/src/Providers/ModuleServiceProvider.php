<?php

namespace Webkul\Tma\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Tma\Models\TelegramIdentity;
use Webkul\Tma\Models\UserPasskey;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected $models = [
        TelegramIdentity::class,
        UserPasskey::class,
    ];
}
