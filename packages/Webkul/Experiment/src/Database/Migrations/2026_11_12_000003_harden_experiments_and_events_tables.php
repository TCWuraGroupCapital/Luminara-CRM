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
        if (Schema::hasTable('experiments') && ! Schema::hasColumn('experiments', 'winner_variant_key')) {
            Schema::table('experiments', function (Blueprint $table): void {
                $table->string('winner_variant_key', 32)->nullable()->after('variants');
            });
        }

        if (Schema::hasTable('experiment_events')) {
            Schema::table('experiment_events', function (Blueprint $table): void {
                $table->decimal('value', 20, 4)->default(0)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('experiments') && Schema::hasColumn('experiments', 'winner_variant_key')) {
            Schema::table('experiments', function (Blueprint $table): void {
                $table->dropColumn('winner_variant_key');
            });
        }

        if (Schema::hasTable('experiment_events')) {
            Schema::table('experiment_events', function (Blueprint $table): void {
                $table->decimal('value', 12, 4)->default(0)->change();
            });
        }
    }
};
