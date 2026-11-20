<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Webkul\Workspace\WorkspaceManager;

/**
 * Holds the hash of the link emailed to a new owner while their address is unconfirmed.
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
            $table->string('verification_token', 64)->nullable()->unique()->after('status_token');
        });
    }

    public function down(): void
    {
        if (app(WorkspaceManager::class)->current() !== null) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropUnique(['verification_token']);
            $table->dropColumn('verification_token');
        });
    }
};
