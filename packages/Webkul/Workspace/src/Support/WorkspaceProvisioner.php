<?php

namespace Webkul\Workspace\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Webkul\Installer\Database\Seeders\DatabaseSeeder as KrayinDatabaseSeeder;
use Webkul\Installer\Helpers\DatabaseManager;
use Webkul\User\Models\Role;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\Models\WorkspaceMember;
use Webkul\Workspace\WorkspaceManager;

/**
 * Creates a workspace in two steps: `register` reserves the name and the owner's email in the
 * landlord database (fast, done inside the sign-up request), `provision` builds the workspace
 * database (slow, done by the queue).
 */
class WorkspaceProvisioner
{
    public function __construct(private WorkspaceManager $manager) {}

    /**
     * Reserve a workspace for a new business. The password is stored only as a hash and is
     * removed from the landlord database as soon as the owner's account exists.
     */
    public function register(string $businessName, string $ownerName, string $ownerEmail, string $password, ?string $verificationToken = null): Workspace
    {
        $ownerEmail = WorkspaceManager::normaliseEmail($ownerEmail);

        return DB::connection(WorkspaceManager::LANDLORD)->transaction(function () use ($businessName, $ownerName, $ownerEmail, $password, $verificationToken) {
            /**
             * An earlier sign-up for this address that was never confirmed gives way to this one.
             */
            Workspace::query()
                ->where('owner_email', $ownerEmail)
                ->where('status', Workspace::STATUS_UNVERIFIED)
                ->get()
                ->each(fn (Workspace $stale) => $stale->delete());

            $workspace = Workspace::create([
                'name' => $businessName,
                'slug' => $this->uniqueSlug($businessName),
                'status' => $verificationToken === null ? Workspace::STATUS_PROVISIONING : Workspace::STATUS_UNVERIFIED,
                'verification_token' => $verificationToken === null ? null : Workspace::hashVerificationToken($verificationToken),
                'owner_name' => $ownerName,
                'owner_email' => $ownerEmail,
                'owner_password' => Hash::make($password),
                'status_token' => Str::random(48),
            ]);

            $workspace->update(['database' => $this->manager->databaseNameFor($workspace)]);

            WorkspaceMember::create(['workspace_id' => $workspace->id, 'email' => $ownerEmail]);

            return $workspace;
        });
    }

    /**
     * Build the workspace database: schema, reference data and the owner's administrator account.
     * On failure nothing is left behind and the email address is free to sign up again.
     */
    public function provision(Workspace $workspace): void
    {
        if ($workspace->status !== Workspace::STATUS_PROVISIONING || ! is_string($workspace->database)) {
            return;
        }

        try {
            $this->manager->createDatabase($workspace->database);

            $this->manager->run($workspace, function () use ($workspace): void {
                if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                    throw new RuntimeException('Migration failed: '.trim(Artisan::output()));
                }

                if (DB::table('roles')->count() === 0
                    && Artisan::call('db:seed', ['--class' => KrayinDatabaseSeeder::class, '--force' => true]) !== 0) {
                    throw new RuntimeException('Seeding failed: '.trim(Artisan::output()));
                }

                $role = Role::query()->where('permission_type', 'all')->first()
                    ?? Role::create(['name' => 'Administrator', 'description' => 'Administrator role', 'permission_type' => 'all']);

                /**
                 * Written with the query builder so the stored hash is used as it is, and so the
                 * seeded default administrator (admin@example.com / a known password) is replaced
                 * by the owner instead of being left behind.
                 */
                $owner = [
                    'name' => $workspace->owner_name,
                    'email' => $workspace->owner_email,
                    'password' => $workspace->owner_password,
                    'status' => 1,
                    'role_id' => $role->id,
                    'view_permission' => 'global',
                    'updated_at' => now(),
                ];

                $seeded = DB::table('users')->orderBy('id')->first();

                $seeded === null
                    ? DB::table('users')->insert($owner + ['created_at' => now()])
                    : DB::table('users')->where('id', $seeded->id)->update($owner);

                DB::table('users')->where('email', '!=', $workspace->owner_email)->delete();

                app(DatabaseManager::class)->markInstallationCompleted();
            });

            $workspace->update([
                'status' => Workspace::STATUS_ACTIVE,
                'owner_password' => null,
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->manager->switchToLandlord();

            try {
                $this->manager->dropDatabase($workspace->database);
            } catch (Throwable $dropException) {
                report($dropException);
            }

            WorkspaceMember::query()->where('workspace_id', $workspace->id)->delete();

            $workspace->update([
                'status' => Workspace::STATUS_FAILED,
                'owner_password' => null,
                'error' => Str::limit($exception->getMessage(), 1000),
            ]);
        }
    }

    /**
     * Remove a workspace for good: its database, its files and its sign-in directory entries.
     */
    public function destroy(Workspace $workspace): void
    {
        $this->manager->switchToLandlord();

        if (is_string($workspace->database) && $workspace->database !== '') {
            $this->manager->dropDatabase($workspace->database);
        }

        foreach ($this->manager->diskRootsFor($workspace) as $root) {
            File::deleteDirectory($root);
        }

        WorkspaceMember::query()->where('workspace_id', $workspace->id)->delete();

        $workspace->delete();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'workspace', 40, '');
        $slug = $base;

        for ($suffix = 2; Workspace::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
