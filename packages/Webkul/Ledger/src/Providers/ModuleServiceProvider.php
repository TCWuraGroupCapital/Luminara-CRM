<?php

namespace Webkul\Ledger\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Ledger\Models\DataBlock;
use Webkul\Ledger\Models\LedgerEpoch;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected $models = [
        DataBlock::class,
        LedgerEpoch::class,
    ];
}
