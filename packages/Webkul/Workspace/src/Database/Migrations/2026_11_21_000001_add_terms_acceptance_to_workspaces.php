<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Webkul\Workspace\WorkspaceManager;

/**
 * Records which Terms of Service and Privacy Policy an owner accepted at sign-up, and when.
 * Landlord database only, like the table it extends.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app(WorkspaceManager::class)->current() !== null) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('terms_version', 64)->nullable()->after('verification_token');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_version');
        });
    }

    public function down(): void
    {
        if (app(WorkspaceManager::class)->current() !== null) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['terms_version', 'terms_accepted_at']);
        });
    }
};
