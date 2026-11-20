<?php

namespace Webkul\Workspace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;
use Webkul\Workspace\Jobs\ProvisionWorkspace;
use Webkul\Workspace\Mail\VerifySignUp;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\Models\WorkspaceMember;
use Webkul\Workspace\Support\WorkspaceProvisioner;
use Webkul\Workspace\WorkspaceManager;

class SignUpController extends Controller
{
    public function __construct(
        private WorkspaceManager $manager,
        private WorkspaceProvisioner $provisioner
    ) {}

    public function create(): View|RedirectResponse
    {
        $this->ensureSignUpIsOpen();

        if (auth()->guard('user')->check()) {
            return redirect()->route('admin.dashboard.index');
        }

        return view('workspace::signup', ['needsInvite' => config('workspaces.signup') === 'invite']);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSignUpIsOpen();

        $data = $request->validate([
            'business_name' => 'required|string|min:2|max:80',
            'name' => 'required|string|min:2|max:80',
            'email' => 'required|email:rfc|max:190',
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'invite_code' => 'nullable|string|max:120',
        ]);

        if (config('workspaces.signup') === 'invite') {
            $expected = (string) config('workspaces.invite_code');

            if ($expected === '' || ! hash_equals($expected, (string) ($data['invite_code'] ?? ''))) {
                throw ValidationException::withMessages(['invite_code' => trans('workspace::app.validation.invite-code')]);
            }
        }

        if (strtolower($data['email']) === 'admin@example.com' || $this->emailHasAccount($data['email'])
            || $this->landlordHasUser($data['email'])) {
            throw ValidationException::withMessages(['email' => trans('workspace::app.validation.email-taken')]);
        }

        if (Workspace::query()->where('status', '!=', Workspace::STATUS_FAILED)->count() >= (int) config('workspaces.limit')) {
            throw ValidationException::withMessages(['business_name' => trans('workspace::app.validation.limit')]);
        }

        if (! $this->manager->verifiesEmail()) {
            $workspace = $this->provisioner->register($data['business_name'], $data['name'], $data['email'], $data['password']);

            ProvisionWorkspace::dispatch($workspace->id);

            return redirect()->route('workspace.signup.status', $workspace->status_token);
        }

        /**
         * Nothing is built until the owner proves the address is theirs. Only a hash of the
         * emailed token is stored.
         */
        $token = Str::random(48);

        $workspace = $this->provisioner->register($data['business_name'], $data['name'], $data['email'], $data['password'], $token);

        try {
            Mail::to($workspace->owner_email)->send(new VerifySignUp($workspace, $token));
        } catch (Throwable $exception) {
            report($exception);

            $workspace->delete();

            throw ValidationException::withMessages(['email' => trans('workspace::app.validation.mail-failed')]);
        }

        return redirect()->route('workspace.signup.status', $workspace->status_token);
    }

    /**
     * The link from the confirmation email. Opening it starts the build.
     */
    public function verify(string $token): View|RedirectResponse
    {
        $this->ensureSignUpIsOpen();

        $workspace = Workspace::query()
            ->where('verification_token', Workspace::hashVerificationToken($token))
            ->where('status', Workspace::STATUS_UNVERIFIED)
            ->first();

        if ($workspace === null || $workspace->created_at?->lt(now()->subHours(Workspace::VERIFICATION_HOURS))) {
            $workspace?->delete();

            return view('workspace::expired');
        }

        $workspace->update(['status' => Workspace::STATUS_PROVISIONING, 'verification_token' => null]);

        ProvisionWorkspace::dispatch($workspace->id);

        return redirect()->route('workspace.signup.status', $workspace->status_token);
    }

    /**
     * The page a new owner waits on while their workspace is built. The token in the address is
     * random and known only to the browser that signed up.
     */
    public function status(Request $request, string $token): View|JsonResponse
    {
        abort_unless($this->manager->enabled(), 404);

        $workspace = Workspace::query()->where('status_token', $token)->firstOrFail();

        if ($request->expectsJson()) {
            return response()->json(['status' => $workspace->status]);
        }

        return view('workspace::status', ['workspace' => $workspace]);
    }

    private function ensureSignUpIsOpen(): void
    {
        abort_unless($this->manager->enabled() && in_array(config('workspaces.signup'), ['open', 'invite'], true), 404);
    }

    /**
     * Whether the address already signs in to a workspace. A sign-up that was never confirmed
     * does not count: its owner may simply be asking for a new link.
     */
    private function emailHasAccount(string $email): bool
    {
        $email = WorkspaceManager::normaliseEmail($email);

        $unconfirmed = Workspace::query()
            ->where('owner_email', $email)
            ->where('status', Workspace::STATUS_UNVERIFIED)
            ->pluck('id');

        return WorkspaceMember::query()->where('email', $email)->whereNotIn('workspace_id', $unconfirmed)->exists();
    }

    private function landlordHasUser(string $email): bool
    {
        return DB::connection(WorkspaceManager::LANDLORD)
            ->table('users')
            ->where('email', WorkspaceManager::normaliseEmail($email))
            ->exists();
    }
}
