<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_customer_facts', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 24);
            $table->unsignedInteger('entity_id');
            $table->string('category', 24);
            $table->string('fact', 280);
            $table->string('visibility', 16)->default('team');
            $table->string('status', 16)->default('active');
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('owner_user_id')->nullable();
            $table->unsignedInteger('approved_by_user_id')->nullable();
            $table->unsignedBigInteger('source_call_id')->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'status'], 'agent_customer_facts_entity_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_customer_facts');
    }
};
