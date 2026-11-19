<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_blocks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('block_height')->unique();
            $table->string('receipt_code', 30)->unique();
            $table->char('prev_block_hash', 64);
            $table->char('block_hash', 64)->unique();
            $table->string('event_name', 96)->index();
            $table->string('entity_type', 64);
            $table->string('entity_id', 64);
            $table->string('actor_kind', 24);
            $table->unsignedInteger('actor_id')->nullable();
            $table->unsignedBigInteger('agent_passport_id')->nullable()->index();
            $table->json('canonical_payload');
            $table->string('signer_alg', 16);
            $table->string('signer_pubkey', 255);
            $table->string('signer_proof', 512);
            $table->unsignedBigInteger('epoch_id')->nullable()->index();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['entity_type', 'entity_id', 'block_height'], 'idx_data_blocks_entity_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_blocks');
    }
};
