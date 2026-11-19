<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gourwest_integrations', function (Blueprint $table): void {
            $table->id();
            $table->string('system_code', 48)->unique();
            $table->string('name', 120);
            $table->string('vendor_owner', 80);
            $table->string('contract_version', 24)->default('v1.2.0');
            $table->string('sync_mode', 32);
            $table->string('status', 24)->default('healthy')->index();
            $table->integer('sla_target_ms')->default(3000);
            $table->integer('last_latency_ms')->default(42);
            $table->bigInteger('records_synced_24h')->default(0);
            $table->integer('failed_events_count')->default(0);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gourwest_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('integration_id')->index();
            $table->char('region_code', 2)->index();
            $table->string('event_type', 64);
            $table->string('external_ref', 80)->index();
            $table->char('payload_hash', 64);
            $table->string('status', 24)->index();
            $table->integer('latency_ms')->default(0);
            $table->string('error_message', 255)->nullable();
            $table->timestamps();

            $table->foreign('integration_id')->references('id')->on('gourwest_integrations')->onDelete('cascade');
            $table->index(['integration_id', 'status'], 'gw_sync_events_int_status_idx');
        });

        Schema::create('gourwest_service_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->unsignedInteger('person_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->char('region_code', 2)->index();
            $table->string('channel', 32);
            $table->string('category', 40);
            $table->string('priority', 16)->default('medium');
            $table->string('status', 24)->default('open')->index();
            $table->string('subject', 191);
            $table->text('summary')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('person_id')->references('id')->on('persons')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['region_code', 'status', 'priority'], 'gw_tickets_region_status_prio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gourwest_service_tickets');
        Schema::dropIfExists('gourwest_sync_events');
        Schema::dropIfExists('gourwest_integrations');
    }
};
