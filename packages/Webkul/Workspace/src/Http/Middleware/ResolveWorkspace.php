<?php

namespace Webkul\Workspace\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\Support\PublicLinks;
use Webkul\Workspace\WorkspaceManager;

/**
 * Chooses the workspace database for a request before anything reads a record.
 *
 * The workspace comes from one of four places, in this order:
 *  0. a public link (`/w/{slug}/...`), which is always served as a signed-out visitor;
 *  1. the signed-in session, which the server wrote at sign-in and the browser cannot alter;
 *  2. for API calls with a bearer token, the workspace header, which only picks the database the
 *     token is then checked against;
 *  3. for the sign-in and password-reset forms, the email address being submitted.
 *
 * Anything else runs against the main database.
 */
class ResolveWorkspace
{
    private const GUEST_ROUTES = [
        'admin.session.store',
        'admin.forgot_password.store',
        'admin.reset_password.store',
    ];

    public function __construct(private WorkspaceManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->manager->enabled()) {
            /**
             * The `/w/{slug}/...` addresses exist only while workspaces are on.
             */
            abort_if($request->route(PublicLinks::PARAMETER) !== null, 404);

            return $next($request);
        }

        /**
         * Start every request from the main database. A long-lived process (tests, workers) may
         * still be pointing at the workspace of the request before this one.
         */
        $this->manager->switchToLandlord();

        if (is_string($slug = $request->route(PublicLinks::PARAMETER))) {
            return $this->servePublicLink($request, $next, $slug);
        }

        $key = (string) config('workspaces.session_key');
        $signingIn = false;
        $workspace = null;

        if ($request->hasSession() && ($id = $request->session()->get($key)) !== null) {
            $workspace = Workspace::query()->active()->find($id);

            if ($workspace === null) {
                /**
                 * The workspace was suspended or removed. Dropping the session matters: keeping
                 * its user id while falling back to the main database would sign the visitor in
                 * as whoever holds that id there.
                 */
                $request->session()->invalidate();

                return $request->expectsJson()
                    ? response()->json(['message' => 'This workspace is not available.'], 401)
                    : redirect()->route('admin.session.create');
            }
        } elseif ($request->bearerToken() !== null && is_string($slug = $request->header((string) config('workspaces.header'))) && $slug !== '') {
            $workspace = Workspace::query()->active()->where('slug', $slug)->first();

            if ($workspace === null) {
                return response()->json(['message' => 'Unknown workspace.'], 401);
            }
        } elseif (
            $request->isMethod('POST')
            && $request->routeIs(...self::GUEST_ROUTES)
            && is_string($email = $request->input('email'))
            /**
             * Someone already signed in to the main database keeps talking to it. Otherwise their
             * session's user id would be looked up in the workspace of whatever email they typed.
             */
            && ! auth()->guard('user')->check()
        ) {
            $workspace = $this->manager->findByEmail($email);
            $signingIn = $workspace !== null && $request->hasSession();
        }

        if ($workspace !== null) {
            $this->manager->switchTo($workspace);
        }

        $response = $next($request);

        /**
         * Bind the session to the workspace only once the password has been accepted there, and
         * only for the very account whose email chose the workspace.
         */
        $user = $signingIn ? auth()->guard('user')->user() : null;
        $signedInAs = $user?->getAttribute('email');
        $submitted = $request->input('email');

        if (
            $workspace !== null
            && is_string($signedInAs)
            && is_string($submitted)
            && WorkspaceManager::normaliseEmail($signedInAs) === WorkspaceManager::normaliseEmail($submitted)
        ) {
            $request->session()->put($key, $workspace->id);
        }

        return $response;
    }

    /**
     * A public page of one business. Whoever is looking at it may be signed in to another
     * business in the same browser; their session's user id means nothing in this database, so
     * the request is served as a visitor and the session is left exactly as it was.
     */
    private function servePublicLink(Request $request, Closure $next, string $slug): Response
    {
        $workspace = Workspace::query()->active()->where('slug', $slug)->first();

        abort_if($workspace === null, 404);

        $request->route()?->forgetParameter(PublicLinks::PARAMETER);
        $request->attributes->set(PublicLinks::REQUEST_FLAG, true);

        $this->manager->switchTo($workspace);

        $guard = auth()->guard('user');

        if ($guard instanceof SessionGuard) {
            Closure::bind(function (): void {
                $this->user = null;
                $this->loggedOut = true;
            }, $guard, SessionGuard::class)();
        }

        return $next($request);
    }
}
