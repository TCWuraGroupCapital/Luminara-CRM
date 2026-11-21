<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a block's payload be removed for a deletion request while the chain stays verifiable:
 * the hash, signature and Merkle proof never depended on reading the payload back, and each
 * redaction is attested by a later signed `ledger.redaction` block that names it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_blocks', function (Blueprint $table) {
            $table->timestamp('redacted_at', 6)->nullable()->after('epoch_id');
            $table->string('redaction_receipt', 30)->nullable()->after('redacted_at');
        });
    }

    public function down(): void
    {
        Schema::table('data_blocks', function (Blueprint $table) {
            $table->dropColumn(['redacted_at', 'redaction_receipt']);
        });
    }
};
