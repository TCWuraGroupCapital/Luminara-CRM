<?php

use App\Providers\AppServiceProvider;
use Barryvdh\DomPDF\ServiceProvider;
use Konekt\Concord\ConcordServiceProvider;
use Prettus\Repository\Providers\RepositoryServiceProvider;
use Webkul\Activity\Providers\ActivityServiceProvider;
use Webkul\Admin\Providers\AdminServiceProvider;
use Webkul\Agent\Providers\AgentServiceProvider;
use Webkul\Attribute\Providers\AttributeServiceProvider;
use Webkul\Automation\Providers\WorkflowServiceProvider;
use Webkul\Contact\Providers\ContactServiceProvider;
use Webkul\Core\Providers\CoreServiceProvider;
use Webkul\DataGrid\Providers\DataGridServiceProvider;
use Webkul\DataTransfer\Providers\DataTransferServiceProvider;
use Webkul\Email\Providers\EmailServiceProvider;
use Webkul\EmailTemplate\Providers\EmailTemplateServiceProvider;
use Webkul\Experiment\Providers\ExperimentServiceProvider;
use Webkul\GoogleContact\Providers\GoogleContactServiceProvider;
use Webkul\Gourwest\Providers\GourwestServiceProvider;
use Webkul\Installer\Providers\InstallerServiceProvider;
use Webkul\Lead\Providers\LeadServiceProvider;
use Webkul\Ledger\Providers\LedgerServiceProvider;
use Webkul\Marketing\Providers\MarketingServiceProvider;
use Webkul\Product\Providers\ProductServiceProvider;
use Webkul\Quote\Providers\QuoteServiceProvider;
use Webkul\Security\Providers\SecurityServiceProvider;
use Webkul\Tag\Providers\TagServiceProvider;
use Webkul\Tma\Providers\TmaServiceProvider;
use Webkul\User\Providers\UserServiceProvider;
use Webkul\Warehouse\Providers\WarehouseServiceProvider;
use Webkul\WebForm\Providers\WebFormServiceProvider;

return [
    /*
     * Package Service Providers...
     */
    ServiceProvider::class,
    ConcordServiceProvider::class,
    RepositoryServiceProvider::class,

    /*
     * Application Service Providers...
     */
    AppServiceProvider::class,

    /*
     * Webkul Service Providers...
     */
    ActivityServiceProvider::class,
    AdminServiceProvider::class,
    AttributeServiceProvider::class,
    WorkflowServiceProvider::class,
    ContactServiceProvider::class,
    CoreServiceProvider::class,
    DataGridServiceProvider::class,
    DataTransferServiceProvider::class,
    EmailTemplateServiceProvider::class,
    EmailServiceProvider::class,
    ExperimentServiceProvider::class,
    GoogleContactServiceProvider::class,
    GourwestServiceProvider::class,
    MarketingServiceProvider::class,
    InstallerServiceProvider::class,
    LeadServiceProvider::class,
    ProductServiceProvider::class,
    QuoteServiceProvider::class,
    SecurityServiceProvider::class,
    LedgerServiceProvider::class,
    AgentServiceProvider::class,
    TmaServiceProvider::class,
    TagServiceProvider::class,
    UserServiceProvider::class,
    WarehouseServiceProvider::class,
    WebFormServiceProvider::class,
];
