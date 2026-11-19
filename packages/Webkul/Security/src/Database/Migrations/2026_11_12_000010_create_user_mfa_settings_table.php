<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mfa_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            $table->text('secret_encrypted');
            $table->json('recovery_codes_hashes')->nullable();
            $table->boolean('mfa_enabled')->default(false)->index();
            $table->unsignedBigInteger('last_used_slice')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mfa_settings');
    }
};
