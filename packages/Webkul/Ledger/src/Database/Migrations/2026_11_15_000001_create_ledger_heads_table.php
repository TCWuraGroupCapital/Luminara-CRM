<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_heads', function (Blueprint $table) {
            $table->string('stream_key', 64)->primary();
            $table->unsignedBigInteger('head_height')->default(0);
            $table->char('head_hash', 64);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_heads');
    }
};
