<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a person's personal data was erased on request. The row itself stays so deals and quotes
 * that must be kept still have a contact to point at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->timestamp('erased_at')->nullable()->after('unique_id');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn('erased_at');
        });
    }
};
