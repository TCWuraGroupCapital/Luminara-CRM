<?php

namespace Webkul\Agent\Providers;

use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentCustomerFact;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Agent\Models\AgentPlaybookBlock;
use Webkul\Agent\Models\AgentPreferenceSignal;
use Webkul\Core\Providers\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected $models = [
        AgentActionCall::class,
        AgentCustomerFact::class,
        AgentPassport::class,
        AgentPlaybookBlock::class,
        AgentPreferenceSignal::class,
    ];
}
