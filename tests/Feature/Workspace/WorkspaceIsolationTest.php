<?php

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Webkul\Agent\Http\Middleware\AuthenticateAgentToken;
use Webkul\Contact\Models\Person;
use Webkul\Sso\Http\Controllers\SsoController;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\Workspace\Jobs\ProvisionWorkspace;
use Webkul\Workspace\Mail\VerifySignUp;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\Models\WorkspaceMember;
use Webkul\Workspace\Support\WorkspaceProvisioner;
use Webkul\Workspace\WorkspaceManager;

/**
 * Two businesses sign up and each is checked for any way to reach the other's records.
 *
 * Every workspace is a real database and building one runs every migration, so the two
 * businesses are signed up once and shared by the tests in this file. They are dropped when the
 * process ends, and any left behind by an interrupted run are dropped before the next one starts.
 */
/**
 * Generated per run, so no password is written into the repository for a secret scanner to
 * flag. It has upper and lower case, digits and a symbol to satisfy the password rules.
 */
if (! defined('WS_PASSWORD')) {
    define('WS_PASSWORD', 'Ws-'.bin2hex(random_bytes(8)).'-Aa9');
}

function workspaceManager(): WorkspaceManager
{
    return app(WorkspaceManager::class);
}

function dropTestWorkspaces(): void
{
    $manager = workspaceManager();

    $manager->switchToLandlord();

    foreach (Workspace::query()->where('owner_email', 'like', '%.isolation.test')->get() as $workspace) {
        if (is_string($workspace->database)) {
            $manager->dropDatabase($workspace->database);
        }

        $workspace->delete();
    }

    WorkspaceMember::query()->where('email', 'like', '%.isolation.test')->delete();
}

function signUpBusiness(string $business, string $email): void
{
    workspaceManager()->switchToLandlord();

    test()->post(route('workspace.signup.store'), [
        'business_name' => $business,
        'name' => $business.' Owner',
        'email' => $email,
        'password' => WS_PASSWORD,
        'password_confirmation' => WS_PASSWORD,
    ])->assertRedirect();

    test()->flushSession();
}

/**
 * Acme and Bravo, signed up through the public form the first time they are asked for.
 *
 * @return array{0: Workspace, 1: Workspace}
 */
function businesses(): array
{
    static $ready = false;

    if (! $ready) {
        dropTestWorkspaces();

        signUpBusiness('Acme Bakery', 'owner@acme.isolation.test');
        signUpBusiness('Bravo Cafe', 'owner@bravo.isolation.test');

        $connection = config('database.connections.'.WorkspaceManager::LANDLORD);

        register_shutdown_function(static function () use ($connection): void {
            try {
                $pdo = new PDO(
                    "mysql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}",
                    $connection['username'],
                    $connection['password']
                );

                $databases = $pdo->query("SELECT `database` FROM workspaces WHERE owner_email LIKE '%.isolation.test'")->fetchAll(PDO::FETCH_COLUMN);

                foreach ($databases as $database) {
                    if (is_string($database) && preg_match('/^[A-Za-z0-9_]+_ws_[0-9]+$/', $database)) {
                        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
                    }
                }

                $pdo->exec("DELETE FROM workspace_members WHERE email LIKE '%.isolation.test'");
                $pdo->exec("DELETE FROM workspaces WHERE owner_email LIKE '%.isolation.test'");
            } catch (Throwable) {
                // Nothing to clean, or the database has already gone away.
            }
        });

        $ready = true;
    }

    workspaceManager()->switchToLandlord();

    return [
        Workspace::query()->where('owner_email', 'owner@acme.isolation.test')->firstOrFail(),
        Workspace::query()->where('owner_email', 'owner@bravo.isolation.test')->firstOrFail(),
    ];
}

function signIn(string $email, string $password = WS_PASSWORD)
{
    test()->flushSession();
    auth()->guard('user')->logout();
    app('auth')->forgetGuards();

    return test()->post(route('admin.session.store'), ['email' => $email, 'password' => $password]);
}

function workspaceInSession(): mixed
{
    return session(config('workspaces.session_key'));
}

beforeEach(function () {
    config([
        'workspaces.enabled' => true,
        'workspaces.signup' => 'open',
        'workspaces.verify_email' => false,
        'workspaces.limit' => 50,
        'queue.default' => 'sync',
        'security.require_admin_mfa' => false,
    ]);
});

it('gives every business that signs up its own database and owner account', function () {
    [$acme, $bravo] = businesses();

    expect($acme->status)->toBe(Workspace::STATUS_ACTIVE)
        ->and($bravo->status)->toBe(Workspace::STATUS_ACTIVE)
        ->and($acme->database)->not->toBe($bravo->database)
        ->and($acme->database)->not->toBe(workspaceManager()->landlordDatabase())
        ->and($acme->owner_password)->toBeNull();

    workspaceManager()->run($acme, function () {
        expect(User::query()->where('email', 'owner@acme.isolation.test')->exists())->toBeTrue()
            ->and(User::query()->where('email', 'admin@example.com')->exists())->toBeFalse()
            ->and(User::query()->where('email', 'owner@bravo.isolation.test')->exists())->toBeFalse()
            ->and(DB::table('lead_pipeline_stages')->count())->toBeGreaterThan(0);
    });
});

it('signs each owner in to their own workspace and nowhere else', function () {
    [$acme, $bravo] = businesses();

    $acmePersonId = workspaceManager()->run($acme, fn () => Person::create([
        'name' => 'Acme Secret Customer',
        'emails' => [['value' => 'vip@acme.isolation.test', 'label' => 'work']],
    ])->id);

    signIn('owner@bravo.isolation.test')->assertRedirect();

    expect(workspaceInSession())->toBe($bravo->id);

    $this->get(route('admin.contacts.persons.view', $acmePersonId))->assertNotFound();

    $this->getJson(route('admin.contacts.persons.search', ['query' => 'Acme']))
        ->assertOk()
        ->assertDontSee('Acme Secret Customer');

    signIn('owner@acme.isolation.test')->assertRedirect();

    expect(workspaceInSession())->toBe($acme->id);

    $this->get(route('admin.contacts.persons.view', $acmePersonId))->assertOk()->assertSee('Acme Secret Customer');
});

it('rejects a wrong password and leaves the visitor signed out of every workspace', function () {
    businesses();

    signIn('owner@acme.isolation.test', 'not-the-password');

    expect(auth()->guard('user')->check())->toBeFalse()
        ->and(workspaceInSession())->toBeNull();
});

it('does not let a session from the main database turn into a workspace session', function () {
    businesses();

    $mainAdmin = getDefaultAdmin();

    $this->actingAs($mainAdmin, 'user')
        ->post(route('admin.session.store'), ['email' => 'owner@acme.isolation.test', 'password' => 'wrong-password']);

    expect(workspaceInSession())->toBeNull();

    $this->actingAs($mainAdmin, 'user')
        ->post(route('admin.session.store'), ['email' => 'owner@acme.isolation.test', 'password' => WS_PASSWORD]);

    expect(workspaceInSession())->toBeNull();
});

it('keeps agent memory, action history and API tokens inside the workspace', function () {
    [$acme, $bravo] = businesses();

    $token = workspaceManager()->run($acme, function () {
        DB::table('agent_playbook_blocks')->updateOrInsert(
            ['slot' => 'pricing_guardrails'],
            ['content' => 'Acme never discounts below cost.', 'created_at' => now(), 'updated_at' => now()]
        );

        return User::query()->where('email', 'owner@acme.isolation.test')->firstOrFail()
            ->createToken('agent', [AuthenticateAgentToken::ABILITY])->plainTextToken;
    });

    workspaceManager()->run($bravo, function () {
        expect(DB::table('agent_playbook_blocks')->where('content', 'like', '%Acme%')->count())->toBe(0)
            ->and(DB::table('personal_access_tokens')->count())->toBe(0);
    });

    $header = config('workspaces.header');

    $this->withToken($token)->withHeaders([$header => $acme->slug])->getJson('/api/agent/context')->assertOk();

    app('auth')->forgetGuards();

    $this->withToken($token)->withHeaders([$header => $bravo->slug])->getJson('/api/agent/context')->assertUnauthorized();

    app('auth')->forgetGuards();

    $this->withToken($token)->withHeaders([$header => 'no-such-business'])->getJson('/api/agent/context')->assertUnauthorized();

    app('auth')->forgetGuards();

    $this->withToken($token)->withHeaders([$header => ''])->getJson('/api/agent/context')->assertUnauthorized();
});

it('lets a teammate added by the owner sign in, and keeps one email in one business', function () {
    [$acme, $bravo] = businesses();

    $teammate = fn () => User::create([
        'name' => 'Teammate',
        'email' => 'staff@acme.isolation.test',
        'password' => bcrypt(WS_PASSWORD),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => Role::query()->value('id'),
    ]);

    workspaceManager()->run($acme, fn () => User::query()->where('email', 'staff@acme.isolation.test')->first()?->delete());

    workspaceManager()->run($acme, $teammate);

    expect(WorkspaceMember::query()->where('email', 'staff@acme.isolation.test')->value('workspace_id'))->toBe($acme->id);

    signIn('staff@acme.isolation.test')->assertRedirect();

    expect(workspaceInSession())->toBe($acme->id);

    expect(fn () => workspaceManager()->run($bravo, $teammate))->toThrow(ValidationException::class);

    workspaceManager()->run($acme, fn () => User::query()->where('email', 'staff@acme.isolation.test')->firstOrFail()->delete());

    expect(WorkspaceMember::query()->where('email', 'staff@acme.isolation.test')->exists())->toBeFalse();
});

it('refuses a second sign-up with an email that already has an account', function () {
    businesses();

    $this->post(route('workspace.signup.store'), [
        'business_name' => 'Acme Copycat',
        'name' => 'Copycat',
        'email' => 'OWNER@acme.isolation.test',
        'password' => WS_PASSWORD,
        'password_confirmation' => WS_PASSWORD,
    ])->assertSessionHasErrors('email');

    workspaceManager()->switchToLandlord();

    expect(Workspace::query()->where('owner_email', 'owner@acme.isolation.test')->count())->toBe(1);
});

it('asks for the invite code when sign-up is by invitation', function () {
    config(['workspaces.signup' => 'invite', 'workspaces.invite_code' => 'beta-2026']);

    businesses();

    Bus::fake([ProvisionWorkspace::class]);

    $form = [
        'business_name' => 'Charlie Deli',
        'name' => 'Charlie Owner',
        'email' => 'owner@charlie.isolation.test',
        'password' => WS_PASSWORD,
        'password_confirmation' => WS_PASSWORD,
    ];

    $this->post(route('workspace.signup.store'), $form + ['invite_code' => 'guess'])->assertSessionHasErrors('invite_code');

    workspaceManager()->switchToLandlord();

    expect(Workspace::query()->where('owner_email', 'owner@charlie.isolation.test')->exists())->toBeFalse();

    $this->post(route('workspace.signup.store'), $form + ['invite_code' => 'beta-2026'])->assertSessionHasNoErrors();

    Bus::assertDispatched(ProvisionWorkspace::class);

    workspaceManager()->switchToLandlord();

    Workspace::query()->where('owner_email', 'owner@charlie.isolation.test')->delete();
});

it('requires and records acceptance of the terms when the legal pages are on', function () {
    $legalDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'legal-signup-'.bin2hex(random_bytes(4));
    mkdir($legalDir);
    file_put_contents($legalDir.'/terms.md', "# Terms\n\nText.");
    file_put_contents($legalDir.'/privacy.md', "# Privacy\n\nText.");

    config([
        'workspaces.signup' => 'invite',
        'workspaces.invite_code' => 'beta-2026',
        'legal.enabled' => true,
        'legal.path' => $legalDir,
        'legal.version' => '2026-12-01',
    ]);

    businesses();

    Bus::fake([ProvisionWorkspace::class]);

    $form = [
        'business_name' => 'Delta Diner',
        'name' => 'Delta Owner',
        'email' => 'owner@delta.isolation.test',
        'password' => WS_PASSWORD,
        'password_confirmation' => WS_PASSWORD,
        'invite_code' => 'beta-2026',
    ];

    try {
        $this->get(route('workspace.signup.create'))->assertOk()->assertSee('name="accept_terms"', false);

        $this->post(route('workspace.signup.store'), $form)->assertSessionHasErrors('accept_terms');

        workspaceManager()->switchToLandlord();

        expect(Workspace::query()->where('owner_email', 'owner@delta.isolation.test')->exists())->toBeFalse();

        $this->post(route('workspace.signup.store'), $form + ['accept_terms' => '1'])->assertSessionHasNoErrors();

        workspaceManager()->switchToLandlord();

        $workspace = Workspace::query()->where('owner_email', 'owner@delta.isolation.test')->firstOrFail();

        expect($workspace->terms_version)->toStartWith('2026-12-01#')
            ->and($workspace->terms_accepted_at)->not->toBeNull();

        $workspace->delete();
    } finally {
        array_map('unlink', glob($legalDir.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($legalDir);
    }
});

it('signs in through single sign-on into the business the email belongs to, and never into another', function () {
    [$acme] = businesses();

    Cache::flush();
    config(['sso.enabled' => true, 'sso.allow_private_issuers' => true]);

    $issuer = 'https://idp.isolation.test';
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $rsa = openssl_pkey_get_details($key)['rsa'];
    $b64 = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    $idToken = null;

    Http::fake([
        $issuer.'/.well-known/openid-configuration' => Http::response(['issuer' => $issuer, 'authorization_endpoint' => $issuer.'/authorize', 'token_endpoint' => $issuer.'/token', 'jwks_uri' => $issuer.'/jwks']),
        $issuer.'/jwks' => Http::response(['keys' => [['kty' => 'RSA', 'kid' => 'k', 'alg' => 'RS256', 'n' => $b64($rsa['n']), 'e' => $b64($rsa['e'])]]]),
        $issuer.'/token' => function () use (&$idToken) {
            return Http::response(['id_token' => $idToken]);
        },
    ]);

    // Acme lists both domains, so only the "belongs to another business" check can stop a Bravo account.
    workspaceManager()->run($acme, function () use ($issuer): void {
        foreach (['enabled' => '1', 'issuer' => $issuer, 'client_id' => 'acme-crm', 'client_secret' => 's', 'domains' => 'acme.isolation.test, bravo.isolation.test'] as $name => $value) {
            DB::table('core_config')->updateOrInsert(['code' => 'general.sso.oidc.'.$name], ['value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    $token = static fn (string $email, string $nonce): string => JWT::encode(
        ['iss' => $issuer, 'aud' => 'acme-crm', 'sub' => 'x', 'email' => $email, 'email_verified' => true, 'nonce' => $nonce, 'iat' => time(), 'exp' => time() + 300],
        $pem,
        'RS256',
        'k'
    );

    $this->post(route('admin.sso.start'), ['email' => 'owner@acme.isolation.test'])->assertOk();
    $pending = session(SsoController::SESSION_KEY);

    expect($pending['workspace_id'])->toBe($acme->id);

    $idToken = $token('owner@bravo.isolation.test', $pending['nonce']);

    $this->get(route('admin.sso.callback', ['state' => $pending['state'], 'code' => 'c']))->assertRedirect(route('admin.sso.create'));
    $this->assertGuest('user');

    workspaceManager()->switchToLandlord();
    $this->post(route('admin.sso.start'), ['email' => 'owner@acme.isolation.test'])->assertOk();
    $pending = session(SsoController::SESSION_KEY);
    $idToken = $token('owner@acme.isolation.test', $pending['nonce']);

    $this->get(route('admin.sso.callback', ['state' => $pending['state'], 'code' => 'c']))->assertRedirect(route('admin.dashboard.index'));

    expect(session(config('workspaces.session_key')))->toBe($acme->id)
        ->and(auth()->guard('user')->user()->email)->toBe('owner@acme.isolation.test');

    auth()->guard('user')->logout();
    workspaceManager()->switchToLandlord();
});

it('runs a queued job in the workspace it was dispatched from', function () {
    [$acme] = businesses();

    $seen = null;

    workspaceManager()->run($acme, function () use (&$seen) {
        Bus::dispatch(function () use (&$seen) {
            $seen = DB::connection()->getDatabaseName();
        });
    });

    expect($seen)->toBe($acme->database)
        ->and(workspaceManager()->current())->toBeNull();
});

it('hides sign-up entirely when workspaces are off', function () {
    config(['workspaces.enabled' => false]);

    $this->get('/signup')->assertNotFound();

    $this->post('/signup', [])->assertNotFound();

    $this->get('/w/acme-bakery/receipts/ABC-123')->assertNotFound();
});

it('serves a public page from the business named in its address, and from no other', function () {
    [$acme, $bravo] = businesses();

    workspaceManager()->run($acme, fn () => DB::table('web_forms')->insert([
        'form_id' => 'acmeform01',
        'title' => 'Acme Enquiry',
        'submit_button_label' => 'Send',
        'submit_success_action' => 'message',
        'submit_success_content' => 'Thanks',
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    $path = '/web-forms/forms/acmeform01/form.html';

    $this->get('/w/'.$acme->slug.$path)->assertOk()->assertSee('Acme Enquiry');

    $this->get('/w/'.$bravo->slug.$path)->assertNotFound();
    $this->get($path)->assertNotFound();
    $this->get('/w/no-such-business'.$path)->assertNotFound();

    workspaceManager()->run($acme, fn () => DB::table('web_forms')->where('form_id', 'acmeform01')->delete());
});

it('treats whoever opens a public link as a visitor and leaves their own session alone', function () {
    [$acme, $bravo] = businesses();

    Route::middleware('web')->get('w/{luminara_workspace}/_probe', fn () => response()->json([
        'user' => auth()->guard('user')->id(),
        'database' => DB::connection()->getDatabaseName(),
    ]));

    signIn('owner@bravo.isolation.test')->assertRedirect();

    $this->getJson('/w/'.$acme->slug.'/_probe')
        ->assertOk()
        ->assertExactJson(['user' => null, 'database' => $acme->database]);

    expect(workspaceInSession())->toBe($bravo->id);

    $this->get(route('admin.dashboard.index'))->assertOk();

    expect(DB::connection()->getDatabaseName())->toBe($bravo->database);
});

it('writes public links with the business in the address only inside a workspace', function () {
    [$acme] = businesses();

    $inside = workspaceManager()->run($acme, fn () => [
        route('ledger.receipts.show', ['receiptCode' => 'ABC-123']),
        url('/receipts/ABC-123'),
        route('admin.settings.web_forms.form_js', 'acmeform01'),
        route('admin.leads.index'),
        route('api.ledger.blocks.proof', ['codeOrHash' => 'ABC-123']),
    ]);

    expect($inside[0])->toEndWith('/w/'.$acme->slug.'/receipts/ABC-123')
        ->and($inside[1])->toEndWith('/w/'.$acme->slug.'/receipts/ABC-123')
        ->and($inside[2])->toContain('/w/'.$acme->slug.'/web-forms/forms/acmeform01/form.js')
        ->and($inside[3])->not->toContain('/w/')
        ->and($inside[4])->not->toContain('/w/')
        ->and(route('ledger.receipts.show', ['receiptCode' => 'ABC-123']))->not->toContain('/w/');
});

it('builds nothing until the owner opens the link emailed to them', function () {
    config(['workspaces.verify_email' => true]);

    Mail::fake();
    Bus::fake([ProvisionWorkspace::class]);

    workspaceManager()->switchToLandlord();

    $form = [
        'business_name' => 'Delta Florist',
        'name' => 'Delta Owner',
        'email' => 'owner@delta.isolation.test',
        'password' => WS_PASSWORD,
        'password_confirmation' => WS_PASSWORD,
    ];

    $this->post(route('workspace.signup.store'), $form)->assertRedirect();

    $delta = Workspace::query()->where('owner_email', 'owner@delta.isolation.test')->firstOrFail();

    expect($delta->status)->toBe(Workspace::STATUS_UNVERIFIED);

    Bus::assertNotDispatched(ProvisionWorkspace::class);

    $token = null;

    Mail::assertSent(VerifySignUp::class, function (VerifySignUp $mail) use (&$token) {
        $token = $mail->token;

        return $mail->hasTo('owner@delta.isolation.test');
    });

    expect($delta->verification_token)->toBe(hash('sha256', $token))->not->toBe($token);

    signIn('owner@delta.isolation.test');

    expect(auth()->guard('user')->check())->toBeFalse();

    /* Asking again replaces the unconfirmed sign-up instead of refusing the address. */
    test()->flushSession();
    $this->post(route('workspace.signup.store'), $form)->assertRedirect()->assertSessionHasNoErrors();

    expect(Workspace::query()->where('owner_email', 'owner@delta.isolation.test')->count())->toBe(1);

    $this->get(route('workspace.signup.verify', $token))->assertOk()->assertSee(trans('workspace::app.status.expired-title'));

    Bus::assertNotDispatched(ProvisionWorkspace::class);

    $fresh = null;

    Mail::assertSent(VerifySignUp::class, function (VerifySignUp $mail) use (&$fresh, $token) {
        $fresh = $mail->token !== $token ? $mail->token : $fresh;

        return true;
    });

    $this->get(route('workspace.signup.verify', $fresh))->assertRedirect();

    $delta = Workspace::query()->where('owner_email', 'owner@delta.isolation.test')->firstOrFail();

    expect($delta->status)->toBe(Workspace::STATUS_PROVISIONING)
        ->and($delta->verification_token)->toBeNull();

    Bus::assertDispatched(ProvisionWorkspace::class, 1);

    $delta->delete();
});

it('removes sign-ups that were never confirmed', function () {
    workspaceManager()->switchToLandlord();

    $stale = app(WorkspaceProvisioner::class)->register('Echo Stale', 'Echo', 'owner@echo.isolation.test', WS_PASSWORD, 'stale-token');
    $recent = app(WorkspaceProvisioner::class)->register('Foxtrot Recent', 'Foxtrot', 'owner@foxtrot.isolation.test', WS_PASSWORD, 'recent-token');

    DB::connection(WorkspaceManager::LANDLORD)->table('workspaces')->where('id', $stale->id)->update(['created_at' => now()->subDays(2)]);

    $this->artisan('luminara:workspace:prune')->assertSuccessful();

    expect(Workspace::query()->find($stale->id))->toBeNull()
        ->and(WorkspaceMember::query()->where('email', 'owner@echo.isolation.test')->exists())->toBeFalse()
        ->and(Workspace::query()->find($recent->id))->not->toBeNull();

    $recent->delete();
});

it('locks a suspended business out and lets it back in when resumed', function () {
    [$acme] = businesses();

    signIn('owner@acme.isolation.test')->assertRedirect();

    try {
        $this->artisan('luminara:workspace:suspend', ['slug' => $acme->slug])->assertSuccessful();

        $this->get(route('admin.dashboard.index'))->assertRedirect(route('admin.session.create'));

        expect(workspaceInSession())->toBeNull();

        signIn('owner@acme.isolation.test');

        expect(auth()->guard('user')->check())->toBeFalse();

        $this->get('/w/'.$acme->slug.'/web-forms/forms/anything/form.html')->assertNotFound();
    } finally {
        workspaceManager()->switchToLandlord();

        $this->artisan('luminara:workspace:suspend', ['slug' => $acme->slug, '--resume' => true])->assertSuccessful();
    }

    signIn('owner@acme.isolation.test')->assertRedirect();

    expect(workspaceInSession())->toBe($acme->id);
});

it('deletes a business only when it is suspended and the slug is repeated', function () {
    [$acme] = businesses();

    $this->artisan('luminara:workspace:delete', ['slug' => $acme->slug])->assertFailed();
    $this->artisan('luminara:workspace:delete', ['slug' => $acme->slug, '--confirm' => $acme->slug])->assertFailed();

    expect(Workspace::query()->find($acme->id))->not->toBeNull();

    workspaceManager()->switchToLandlord();

    $golf = app(WorkspaceProvisioner::class)->register('Golf Gone', 'Golf', 'owner@golf.isolation.test', WS_PASSWORD);
    $golf->update(['status' => Workspace::STATUS_SUSPENDED]);

    workspaceManager()->createDatabase($golf->database);

    $this->artisan('luminara:workspace:delete', ['slug' => $golf->slug, '--confirm' => $golf->slug])->assertSuccessful();

    $exists = DB::connection(WorkspaceManager::LANDLORD)
        ->select('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$golf->database]);

    expect(Workspace::query()->find($golf->id))->toBeNull()
        ->and(WorkspaceMember::query()->where('email', 'owner@golf.isolation.test')->exists())->toBeFalse()
        ->and($exists)->toBeEmpty();
});

it('welcomes the owner of a new business with first steps', function () {
    [$acme] = businesses();

    signIn('owner@acme.isolation.test')->assertRedirect();

    $this->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertSee(trans('workspace::app.onboarding.title', ['name' => $acme->name]))
        ->assertSee(trans('workspace::app.onboarding.steps.team'));
});
