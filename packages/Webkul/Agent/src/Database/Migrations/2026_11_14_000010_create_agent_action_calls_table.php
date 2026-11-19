<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_action_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('action', 64);
            $table->json('params');
            $table->string('reason', 500)->nullable();
            $table->json('subject')->nullable();
            $table->char('payload_hash', 64);
            $table->string('idempotency_key', 128)->nullable();
            $table->string('status', 24)->index();
            $table->json('result')->nullable();
            $table->string('error_code', 48)->nullable();
            $table->unsignedInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_action_calls');
    }
};
