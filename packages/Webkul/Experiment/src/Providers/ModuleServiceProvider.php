<?php

namespace Webkul\Experiment\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Experiment\Models\Experiment;
use Webkul\Experiment\Models\ExperimentEvent;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected $models = [
        Experiment::class,
        ExperimentEvent::class,
    ];
}
