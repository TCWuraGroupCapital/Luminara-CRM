<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One event per (integration, external_ref): the database backstop for idempotent webhook ingestion (DEF-01).
 * Fails if duplicates already exist; remove them first (none existed in the demo, test or load databases on 18 Nov 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gourwest_sync_events', function (Blueprint $table): void {
            $table->unique(['integration_id', 'external_ref'], 'gw_sync_events_int_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('gourwest_sync_events', function (Blueprint $table): void {
            $table->dropUnique('gw_sync_events_int_ref_unique');
        });
    }
};
