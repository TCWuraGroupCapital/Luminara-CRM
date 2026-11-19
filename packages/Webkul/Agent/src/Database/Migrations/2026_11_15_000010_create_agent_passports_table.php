<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_passports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('token_id')->nullable()->unique();
            $table->string('slug', 64);
            $table->string('name', 120);
            $table->json('allowed_actions')->nullable();
            $table->unsignedInteger('daily_call_limit')->default(1000);
            $table->decimal('auto_approve_value_ceiling', 15, 2)->nullable();
            $table->string('trust_mode', 24)->default('standard');
            $table->unsignedInteger('approved_streak')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->string('webhook_url', 500)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('agent_pubkey', 255)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_passports');
    }
};
