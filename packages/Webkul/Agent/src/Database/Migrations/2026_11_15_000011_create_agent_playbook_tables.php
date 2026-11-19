<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_playbook_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 48)->unique();
            $table->text('content');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('updated_by_user_id')->nullable();
            $table->unsignedBigInteger('source_call_id')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_preference_signals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('call_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 64);
            $table->string('signal_type', 32);
            $table->json('param_diff')->nullable();
            $table->string('feedback_note', 500)->nullable();
            $table->string('distilled_rule', 280)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_preference_signals');
        Schema::dropIfExists('agent_playbook_blocks');
    }
};
