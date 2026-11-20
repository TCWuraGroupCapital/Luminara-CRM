<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Webkul\Workspace\WorkspaceManager;

/**
 * The registry of workspaces lives only in the main (landlord) database. The same migration
 * set also runs inside every workspace database, where these tables would be dead weight,
 * so it does nothing there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app(WorkspaceManager::class)->current() !== null) {
            return;
        }

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('database')->nullable()->unique();
            $table->string('status', 20)->default('provisioning')->index();
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_password')->nullable();
            $table->string('status_token', 64)->unique();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (app(WorkspaceManager::class)->current() !== null) {
            return;
        }

        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
