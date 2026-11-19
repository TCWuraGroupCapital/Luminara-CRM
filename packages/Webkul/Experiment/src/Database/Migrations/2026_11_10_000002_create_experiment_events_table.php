<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('experiment_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('experiment_id');
            $table->string('variant_key', 32);
            $table->char('subject_hash', 64);
            $table->string('event_type', 20);
            $table->string('event_key', 64)->default('default');
            $table->decimal('value', 12, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('experiment_id')->references('id')->on('experiments')->onDelete('cascade');

            $table->unique(
                ['experiment_id', 'subject_hash', 'event_type', 'event_key'],
                'experiment_events_subject_dedup_unique'
            );

            $table->index(
                ['experiment_id', 'variant_key', 'event_type'],
                'experiment_events_analytics_idx'
            );

            $table->index('subject_hash', 'experiment_events_subject_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiment_events');
    }
};
