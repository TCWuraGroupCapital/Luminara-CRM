<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Webkul\Admin\Http\Requests\UserForm;
use Webkul\Security\Support\PasswordPolicy;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * Password policy for accounts people can sign in with.
 *
 * Create, update, self-service change, reset, and luminara:provision all use
 * Password::defaults(): 12 characters, letters, mixed case, and a number.
 * The breached-password lookup is off while APP_ENV is testing.
 */
uses(DatabaseTransactions::class);

const POLICY_PASSWORD = 'LuminaraStaff4821';

beforeEach(function () {
    Mail::fake();

    $this->withoutMiddleware(ThrottleRequests::class);
});

function policyAdmin(): User
{
    return getDefaultAdmin();
}

function policyRoleId(): int
{
    return (int) policyAdmin()->role_id;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function policyUserPayload(array $overrides = []): array
{
    $email = 'policy-'.bin2hex(random_bytes(4)).'@example.test';

    return array_merge([
        'name' => 'Policy Probe',
        'email' => $email,
        'role_id' => policyRoleId(),
        'status' => 1,
        'view_permission' => 'individual',
        'password' => POLICY_PASSWORD,
        'confirm_password' => POLICY_PASSWORD,
    ], $overrides);
}

function policyAccount(string $password = 'OriginalPass123'): User
{
    $role = Role::query()->find(policyRoleId()) ?? Role::query()->firstOrCreate(
        ['name' => 'Administrator'],
        ['description' => 'Administrator role', 'permission_type' => 'all']
    );

    return User::create([
        'name' => 'Policy Account',
        'email' => 'account-'.bin2hex(random_bytes(4)).'@example.test',
        'password' => bcrypt($password),
        'status' => 1,
        'view_permission' => 'individual',
        'role_id' => $role->id,
    ]);
}

it('registers a 12 character mixed password rule and skips the breach check while testing', function () {
    $applied = PasswordRule::defaults()->appliedRules();

    expect($applied['min'])->toBe(12)
        ->and($applied['letters'])->toBeTrue()
        ->and($applied['mixedCase'])->toBeTrue()
        ->and($applied['numbers'])->toBeTrue()
        ->and($applied['uncompromised'])->toBeFalse()
        ->and(PasswordPolicy::make(true)->appliedRules()['uncompromised'])->toBeTrue();
});

it('rejects passwords that miss length, mixed case, or a number', function (string $password) {
    $check = Validator::make(
        ['password' => $password],
        ['password' => PasswordPolicy::rules()]
    );

    expect($check->fails())->toBeTrue()
        ->and($check->errors()->has('password'))->toBeTrue();
})->with([
    'too short' => 'Short1A',
    'no uppercase' => 'alllowercase1234',
    'no lowercase' => 'ALLUPPERCASE1234',
    'no number' => 'MixedCaseOnly',
    'no letter' => '123456789012',
]);

it('accepts a password that meets the policy', function () {
    $check = Validator::make(
        ['password' => POLICY_PASSWORD],
        ['password' => PasswordPolicy::rules()]
    );

    expect($check->fails())->toBeFalse();
});

it('requires a strong password when a user is created', function () {
    $admin = policyAdmin();

    $this->actingAs($admin, 'user')
        ->postJson(route('admin.settings.users.store'), policyUserPayload([
            'password' => null,
            'confirm_password' => null,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    $this->actingAs($admin, 'user')
        ->postJson(route('admin.settings.users.store'), policyUserPayload([
            'password' => 'alllowercase1234',
            'confirm_password' => 'alllowercase1234',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    $email = 'created-'.bin2hex(random_bytes(4)).'@example.test';

    $this->actingAs($admin, 'user')
        ->postJson(route('admin.settings.users.store'), policyUserPayload([
            'email' => $email,
        ]))
        ->assertSuccessful();

    $created = User::query()->where('email', $email)->first();

    expect($created)->not->toBeNull()
        ->and(Hash::check(POLICY_PASSWORD, $created->password))->toBeTrue();
});

it('applies the policy on user update and still allows a blank password', function () {
    $account = policyAccount();

    $this->actingAs(policyAdmin(), 'user')
        ->putJson(route('admin.settings.users.update', $account->id), [
            'name' => $account->name,
            'email' => $account->email,
            'role_id' => $account->role_id,
            'view_permission' => $account->view_permission,
            'status' => 1,
            'password' => 'alllowercase1234',
            'confirm_password' => 'alllowercase1234',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(Hash::check('OriginalPass123', $account->fresh()->password))->toBeTrue();

    $this->actingAs(policyAdmin(), 'user')
        ->putJson(route('admin.settings.users.update', $account->id), [
            'name' => 'Renamed Probe',
            'email' => $account->email,
            'role_id' => $account->role_id,
            'view_permission' => $account->view_permission,
            'status' => 1,
            'password' => '',
            'confirm_password' => '',
        ])
        ->assertSuccessful();

    expect($account->fresh()->name)->toBe('Renamed Probe')
        ->and(Hash::check('OriginalPass123', $account->fresh()->password))->toBeTrue();

    $this->actingAs(policyAdmin(), 'user')
        ->putJson(route('admin.settings.users.update', $account->id), [
            'name' => 'Renamed Probe',
            'email' => $account->email,
            'role_id' => $account->role_id,
            'view_permission' => $account->view_permission,
            'status' => 1,
            'password' => POLICY_PASSWORD,
            'confirm_password' => POLICY_PASSWORD,
        ])
        ->assertSuccessful();

    expect(Hash::check(POLICY_PASSWORD, $account->fresh()->password))->toBeTrue();
});

it('applies the policy when someone changes their own password', function () {
    $current = 'OriginalPass123';
    $account = policyAccount($current);

    $this->actingAs($account, 'user')
        ->putJson(route('admin.user.account.update'), [
            'name' => $account->name,
            'email' => $account->email,
            'current_password' => $current,
            'password' => 'alllowercase1234',
            'password_confirmation' => 'alllowercase1234',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(Hash::check($current, $account->fresh()->password))->toBeTrue();

    $this->actingAs($account, 'user')
        ->put(route('admin.user.account.update'), [
            'name' => 'Renamed Account',
            'email' => $account->email,
            'current_password' => $current,
        ])
        ->assertRedirect();

    expect($account->fresh()->name)->toBe('Renamed Account')
        ->and(Hash::check($current, $account->fresh()->password))->toBeTrue();

    $this->actingAs($account, 'user')
        ->put(route('admin.user.account.update'), [
            'name' => $account->name,
            'email' => $account->email,
            'current_password' => $current,
            'password' => POLICY_PASSWORD,
            'password_confirmation' => POLICY_PASSWORD,
        ])
        ->assertRedirect();

    expect(Hash::check(POLICY_PASSWORD, $account->fresh()->password))->toBeTrue();
});

it('applies the policy when a password is reset', function () {
    $account = policyAccount();
    $token = Password::broker('users')->createToken($account);

    $this->postJson(route('admin.reset_password.store'), [
        'token' => $token,
        'email' => $account->email,
        'password' => 'alllowercase1234',
        'password_confirmation' => 'alllowercase1234',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(Hash::check('OriginalPass123', $account->fresh()->password))->toBeTrue();

    $this->post(route('admin.reset_password.store'), [
        'token' => $token,
        'email' => $account->email,
        'password' => POLICY_PASSWORD,
        'password_confirmation' => POLICY_PASSWORD,
    ])->assertRedirect(route('admin.dashboard.index'));

    expect(Hash::check(POLICY_PASSWORD, $account->fresh()->password))->toBeTrue()
        ->and(auth()->guard('user')->id())->toBe($account->id);
});

it('rejects weak passwords in luminara:provision before any migration', function (string $password) {
    $email = 'provision-'.bin2hex(random_bytes(3)).'@luminaracrm.local';

    expect(Artisan::call('luminara:provision', [
        '--admin-email' => $email,
        '--admin-password' => $password,
        '--no-interaction' => true,
    ]))->toBe(1);

    expect(User::query()->where('email', $email)->exists())->toBeFalse();
})->with([
    'too short' => 'Short1A',
    'no uppercase' => 'alllowercase1234',
    'no number' => 'NoDigitsInThisPassword',
    'known default' => 'admin123',
]);

it('requires a strong password on the user form request', function () {
    $create = UserForm::create('/admin/settings/users/create', 'POST', [
        'name' => 'Form Probe',
        'email' => 'form-'.bin2hex(random_bytes(4)).'@example.test',
        'role_id' => policyRoleId(),
        'password' => 'alllowercase1234',
        'password_confirmation' => 'alllowercase1234',
    ]);
    $create->setContainer(app())->setRedirector(app('redirect'));

    $created = Validator::make($create->all(), $create->rules());

    expect($created->fails())->toBeTrue()
        ->and($created->errors()->has('password'))->toBeTrue();

    $update = UserForm::create('/admin/settings/users/edit/1', 'PUT', [
        'name' => 'Form Probe',
        'email' => 'form-'.bin2hex(random_bytes(4)).'@example.test',
        'role_id' => policyRoleId(),
    ]);
    $update->setContainer(app())->setRedirector(app('redirect'));

    expect(Validator::make($update->all(), $update->rules())->errors()->has('password'))->toBeFalse();
});

it('checks installer administrator passwords with the shared policy', function () {
    $controller = file_get_contents(base_path('packages/Webkul/Installer/src/Http/Controllers/InstallerController.php'));
    $command = file_get_contents(base_path('packages/Webkul/Installer/src/Console/Commands/Installer.php'));

    expect($controller)->toContain('Password::defaults()')
        ->and($command)->toContain('Password::defaults()');
});
