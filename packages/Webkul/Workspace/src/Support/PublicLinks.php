<?php

namespace Webkul\Workspace\Support;

use Illuminate\Routing\Router;
use Webkul\Workspace\WorkspaceManager;

/**
 * Public pages have no session and no token, so nothing says which business they belong to.
 * Each one therefore gets a second address that names the business: `/w/{slug}/...`.
 *
 * The slug is not a secret. It only chooses the database a page that is already public reads
 * from, and a request that arrives this way is always treated as a signed-out visitor.
 */
class PublicLinks
{
    public const PARAMETER = 'luminara_workspace';

    public const REQUEST_FLAG = 'luminara.public_link';

    /**
     * Routes that are reachable without signing in and read or write business records.
     */
    private const ROUTES = [
        'admin.settings.web_forms.form_js',
        'admin.settings.web_forms.preview',
        'admin.settings.web_forms.form_store',
        'ledger.receipts.show',
        'api.ledger.blocks.proof',
        'experiments.public.assign',
        'experiments.public.track',
        'api.gourwest.webhooks.ingest',
    ];

    /**
     * Addresses that are rewritten whenever they are generated inside a workspace.
     */
    private const PATHS = '#^/(receipts/|web-forms/forms/|experiments/(assign/|track$)|api/gourwest/webhooks/)#';

    /**
     * The proof API also answers signed-in users with more detail, so links to it are only
     * rewritten on pages that are themselves public.
     */
    private const PUBLIC_ONLY_PATHS = '#^/api/ledger/blocks/#';

    public function __construct(private WorkspaceManager $manager) {}

    /**
     * Add the `/w/{slug}/...` twin of every public route. Called once all packages have
     * registered their routes; the twins are part of the route cache when one is built.
     */
    public function registerRoutes(Router $router): void
    {
        $routes = $router->getRoutes();

        /**
         * Names given with `->name()` after a route is added are only indexed on a refresh.
         */
        $routes->refreshNameLookups();

        foreach (self::ROUTES as $name) {
            $route = $routes->getByName($name);

            if ($route === null || $routes->hasNamedRoute('workspace.public.'.$name)) {
                continue;
            }

            $action = $route->getAction();
            $action['as'] = 'workspace.public.'.$name;

            /**
             * The original address already contains its group prefix; left in the action it
             * would be applied a second time.
             */
            unset($action['prefix']);

            $router
                ->addRoute($route->methods(), 'w/{'.self::PARAMETER.'}/'.ltrim($route->uri(), '/'), $action)
                ->where($route->wheres + [self::PARAMETER => '[a-z0-9\-]+']);
        }
    }

    /**
     * Rewrite a generated path so it names the current workspace.
     */
    public function path(string $path): string
    {
        $workspace = $this->manager->current();

        if ($workspace === null || ! $this->manager->enabled()) {
            return $path;
        }

        $public = app()->bound('request') && request()->attributes->get(self::REQUEST_FLAG) === true;

        if (preg_match(self::PATHS, $path) === 1 || ($public && preg_match(self::PUBLIC_ONLY_PATHS, $path) === 1)) {
            return '/w/'.$workspace->slug.$path;
        }

        return $path;
    }
}
