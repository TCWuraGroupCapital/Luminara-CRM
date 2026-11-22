<?php

namespace Webkul\Security\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Webkul\Core\Models\CoreConfig;
use Webkul\Installer\Database\Seeders\DatabaseSeeder as KrayinDatabaseSeeder;
use Webkul\Installer\Helpers\DatabaseManager;
use Webkul\Security\Support\CoreConfigEncryptor;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class ProvisionCommand extends Command
{
    protected $signature = 'luminara:provision
                            {--admin-email= : Initial or rotated administrator email}
                            {--admin-password= : Initial or rotated administrator password (12+ characters, mixed case, and a number)}
                            {--admin-name=Luminara Admin : Administrator display name}
                            {--force : Run non-interactively in production}';

    protected $description = 'Idempotently migrate, seed if empty, and provision a strong administrator account without destructive resets.';

    private const FORBIDDEN_PASSWORDS = [
        'admin123',
        'password',
        'password123',
        '12345678',
        'luminara123',
    ];

    public function handle(DatabaseManager $databaseManager): int
    {
        $envEmail = getenv('LUMINARA_ADMIN_EMAIL');
        $envPassword = getenv('LUMINARA_ADMIN_PASSWORD');

        $email = trim((string) ($this->option('admin-email') ?: (is_string($envEmail) ? $envEmail : '')));
        $password = (string) ($this->option('admin-password') ?: (is_string($envPassword) ? $envPassword : ''));
        $name = trim((string) $this->option('admin-name')) ?: 'Luminara Admin';

        if ($password === '' && $this->input->isInteractive() && ! $this->option('no-interaction')) {
            $password = (string) ($this->secret('Enter administrator password (12+ characters, mixed case, and a number)') ?? '');
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strtolower($email) === 'admin@example.com') {
            $this->error('A valid non-default administrator email (--admin-email or LUMINARA_ADMIN_EMAIL) is required.');

            return self::FAILURE;
        }

        $passwordCheck = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]]
        );

        if ($passwordCheck->fails()) {
            $this->error((string) $passwordCheck->errors()->first('password'));

            return self::FAILURE;
        }

        if (in_array(strtolower($password), self::FORBIDDEN_PASSWORDS, true)) {
            $this->error('Administrator password (--admin-password or LUMINARA_ADMIN_PASSWORD) must not be a known default.');

            return self::FAILURE;
        }

        // Non-destructive additive migrations ONLY — never migrate:fresh!
        if (Artisan::call('migrate', ['--force' => true]) !== self::SUCCESS) {
            $this->error('Database migration failed: '.trim(Artisan::output()));

            return self::FAILURE;
        }

        if (DB::table('roles')->count() === 0) {
            if (Artisan::call('db:seed', ['--class' => KrayinDatabaseSeeder::class, '--force' => true]) !== self::SUCCESS) {
                $this->error('Initial database seeding failed: '.trim(Artisan::output()));

                return self::FAILURE;
            }
        }

        $role = Role::query()->where('permission_type', 'all')->first()
            ?? Role::create([
                'name' => 'Administrator',
                'description' => 'Administrator role',
                'permission_type' => 'all',
            ]);

        $existingDefault = User::query()->where('email', 'admin@example.com')->first();
        $targetUser = User::query()->where('email', $email)->first() ?? $existingDefault;

        if ($targetUser) {
            $targetUser->update([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 1,
                'role_id' => $role->id,
                'view_permission' => 'global',
            ]);
        } else {
            $targetUser = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 1,
                'role_id' => $role->id,
                'view_permission' => 'global',
            ]);
        }

        // Ensure no residual seeded admin@example.com account retains status=1 or admin123
        if ($existingDefault && (int) $existingDefault->id !== (int) $targetUser->id) {
            $existingDefault->update([
                'status' => 0,
                'password' => Hash::make(bin2hex(random_bytes(24))),
            ]);
        }

        // Idempotently encrypt any legacy plaintext sensitive core_config secrets on upgrade
        foreach (CoreConfig::query()->cursor() as $configRow) {
            $rawVal = $configRow->getAttributes()['value'] ?? null;
            $code = is_string($configRow->getAttribute('code')) ? $configRow->getAttribute('code') : null;

            if (is_string($rawVal) && $rawVal !== '' && ! str_starts_with($rawVal, CoreConfigEncryptor::PREFIX) && CoreConfigEncryptor::isSensitiveCode($code)) {
                $configRow->save();
            }
        }

        $databaseManager->markInstallationCompleted();
        $databaseManager->clearInstallationInProgress();
        @file_put_contents(storage_path('installed'), 'Luminara CRM provisioned at '.now()->toIso8601String());

        SecurityAuditRecorder::record(
            event: 'system.provisioned',
            auditableType: 'user',
            auditableId: $targetUser->id,
            metadata: ['email' => $email],
            actorId: (int) $targetUser->id,
            actorEmail: $email,
        );

        $this->info("Luminara CRM provisioned cleanly for [{$email}].");

        return self::SUCCESS;
    }
}
