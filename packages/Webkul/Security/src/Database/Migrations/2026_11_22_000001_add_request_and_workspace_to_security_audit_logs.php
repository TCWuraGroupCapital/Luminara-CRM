<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_audit_logs', function (Blueprint $table) {
            $table->string('request_id', 64)->nullable()->index();
            $table->string('workspace', 191)->nullable()->index();
            $table->index('auditable_type');
        });
    }

    public function down(): void
    {
        Schema::table('security_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropIndex(['workspace']);
            $table->dropIndex(['auditable_type']);
            $table->dropColumn(['request_id', 'workspace']);
        });
    }
};
