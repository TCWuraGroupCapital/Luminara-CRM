<?php

namespace Webkul\Security\Providers;

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Core\Models\CoreConfig;
use Webkul\Core\ViewRenderEventManager;
use Webkul\Lead\Models\Lead;
use Webkul\Quote\Models\Quote;
use Webkul\Security\Console\Commands\BackupCommand;
use Webkul\Security\Console\Commands\BackupRestoreCommand;
use Webkul\Security\Console\Commands\BackupVerifyCommand;
use Webkul\Security\Console\Commands\ProvisionCommand;
use Webkul\Security\Http\Middleware\AssignRequestId;
use Webkul\Security\Http\Middleware\AuditDataExport;
use Webkul\Security\Http\Middleware\ContentSecurityPolicy;
use Webkul\Security\Http\Middleware\EnforceAdminMfa;
use Webkul\Security\Listeners\DataExportAuditor;
use Webkul\Security\Listeners\DeepHealthCheckListener;
use Webkul\Security\Listeners\SecurityEventSubscriber;
use Webkul\Security\Observers\EntityAuditObserver;
use Webkul\Security\Support\AuditContext;
use Webkul\Security\Support\CoreConfigEncryptor;
use Webkul\Security\Support\SecurityAuditRecorder;

class SecurityServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        SecurityAuditRecorder::resetState();

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'security');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'security');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        $router->pushMiddlewareToGroup('web', AssignRequestId::class);
        $router->pushMiddlewareToGroup('web', AuditDataExport::class);
        $router->pushMiddlewareToGroup('web', EnforceAdminMfa::class);
        $router->pushMiddlewareToGroup('web', ContentSecurityPolicy::class);
        $router->pushMiddlewareToGroup('api', AssignRequestId::class);
        $router->pushMiddlewareToGroup('api', EnforceAdminMfa::class);

        Event::subscribe(SecurityEventSubscriber::class);

        $this->registerEntityAudit();
        $this->registerAuditLifecycle();

        Event::listen(DiagnosingHealth::class, [DeepHealthCheckListener::class, 'handle']);

        Event::listen('admin.user.account.form.after', static function (ViewRenderEventManager $viewRenderEventManager): void {
            $viewRenderEventManager->addTemplate('security::mfa.account-card');
        });

        $this->registerCoreConfigEncryptionHooks();

        $this->registerBreadcrumbs();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProvisionCommand::class,
                BackupCommand::class,
                BackupVerifyCommand::class,
                BackupRestoreCommand::class,
            ]);
        }

        $this->app->register(ModuleServiceProvider::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/security.php', 'security');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
    }

    private function registerEntityAudit(): void
    {
        $this->app->singleton(EntityAuditObserver::class);

        foreach ([Person::class, Organization::class, Lead::class, Quote::class, Activity::class, AttributeValue::class] as $model) {
            $model::observe(EntityAuditObserver::class);
        }
    }

    private function registerAuditLifecycle(): void
    {
        $auditor = DataExportAuditor::class;

        Event::listen('datagrid.*.export_file.set.before', [$auditor, 'before']);
        Event::listen('datagrid.*.export_file.set.after', [$auditor, 'after']);
        Event::listen(RequestHandled::class, static fn () => SecurityAuditRecorder::flush());
        Event::listen(CommandFinished::class, static fn () => SecurityAuditRecorder::flush());
        Event::listen(JobProcessed::class, static fn () => SecurityAuditRecorder::flush());
        Event::listen(JobExceptionOccurred::class, static fn () => SecurityAuditRecorder::flush());
        Event::listen('workspace.connection.changing', static fn () => SecurityAuditRecorder::flush());

        Event::listen(CommandStarting::class, static function (): void {
            AuditContext::set((string) Str::uuid());
        });

        Event::listen(JobProcessing::class, static function (JobProcessing $event): void {
            AuditContext::set(AuditContext::accept($event->job->payload()['luminara_request_id'] ?? null));
        });

        Queue::createPayloadUsing(static function (): array {
            $id = AuditContext::get();

            return is_string($id) && $id !== '' ? ['luminara_request_id' => $id] : [];
        });
    }

    private function registerCoreConfigEncryptionHooks(): void
    {
        CoreConfig::saving(static function (CoreConfig $config): void {
            $raw = $config->getAttributes()['value'] ?? null;
            $code = $config->getAttribute('code');
            $encrypted = CoreConfigEncryptor::encryptIfNeeded(is_string($code) ? $code : null, $raw);

            $config->setRawAttributes(array_merge($config->getAttributes(), [
                'value' => $encrypted,
            ]), false);
        });

        $decryptInMemory = static function (CoreConfig $config): void {
            $raw = $config->getAttributes()['value'] ?? null;

            if (is_string($raw) && str_starts_with($raw, CoreConfigEncryptor::PREFIX)) {
                $config->setAttribute('value', CoreConfigEncryptor::decryptIfNeeded($raw));
                $config->syncOriginalAttribute('value');
            }
        };

        CoreConfig::saved($decryptInMemory);
        CoreConfig::retrieved($decryptInMemory);
    }

    private function registerBreadcrumbs(): void
    {
        if (! class_exists(Breadcrumbs::class)) {
            return;
        }

        if (! Breadcrumbs::exists('security.mfa')) {
            Breadcrumbs::for('security.mfa', function (BreadcrumbTrail $trail): void {
                if (Breadcrumbs::exists('settings')) {
                    $trail->parent('settings');
                }

                $trail->push(trans('security::app.mfa.title'), route('admin.security.mfa.setup'));
            });
        }

        if (! Breadcrumbs::exists('security.audit_logs')) {
            Breadcrumbs::for('security.audit_logs', function (BreadcrumbTrail $trail): void {
                if (Breadcrumbs::exists('settings')) {
                    $trail->parent('settings');
                }

                $trail->push(trans('security::app.audit.title'), route('admin.security.audit_logs.index'));
            });
        }
    }
}
