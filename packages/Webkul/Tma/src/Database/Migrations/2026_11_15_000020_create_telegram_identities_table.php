<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_identities', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->unique();
            $table->unsignedBigInteger('telegram_user_id')->unique();
            $table->string('telegram_username', 64)->nullable();
            $table->string('first_name', 120)->nullable();
            $table->bigInteger('chat_id')->nullable();
            $table->unsignedInteger('last_auth_date')->nullable();
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_identities');
    }
};
