<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_action_calls', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('agent_action_calls', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('actor_email_snapshot', 191)->nullable()->after('user_id');
            $table->unsignedBigInteger('agent_passport_id')->nullable()->index()->after('token_id');
            $table->json('edited_params')->nullable()->after('params');
            $table->string('human_feedback', 500)->nullable()->after('reason');
            $table->json('signer_proof')->nullable()->after('decided_at');
            $table->timestamp('reviewed_at')->nullable()->after('signer_proof');
            $table->char('block_hash', 64)->nullable()->index()->after('reviewed_at');
            $table->string('receipt_code', 30)->nullable()->index()->after('block_hash');
        });
    }

    public function down(): void
    {
        Schema::table('agent_action_calls', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['agent_passport_id']);
            $table->dropIndex(['block_hash']);
            $table->dropIndex(['receipt_code']);

            $table->dropColumn([
                'actor_email_snapshot',
                'agent_passport_id',
                'edited_params',
                'human_feedback',
                'signer_proof',
                'reviewed_at',
                'block_hash',
                'receipt_code',
            ]);
        });

        Schema::table('agent_action_calls', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
