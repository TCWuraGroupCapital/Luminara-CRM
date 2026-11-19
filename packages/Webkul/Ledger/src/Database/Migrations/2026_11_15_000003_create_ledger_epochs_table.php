<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_epochs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('start_height');
            $table->unsignedBigInteger('end_height');
            $table->unsignedInteger('block_count');
            $table->char('merkle_root', 64)->unique();
            $table->char('prev_epoch_root', 64);
            $table->string('server_signature', 512);
            $table->string('anchor_driver', 32)->default('local');
            $table->string('anchor_reference', 192)->nullable();
            $table->timestamp('sealed_at', 6)->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_epochs');
    }
};
