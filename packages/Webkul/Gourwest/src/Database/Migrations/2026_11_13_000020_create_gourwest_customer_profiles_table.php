<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gourwest_customer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('person_id')->index();
            $table->unsignedInteger('owner_user_id')->nullable()->index();
            $table->string('golden_record_uid', 40)->index();
            $table->char('region_code', 2)->index();
            $table->string('home_outlet', 120);
            $table->string('privacy_regime', 64);
            $table->string('consent_status', 24)->default('verified');
            $table->string('loyalty_member_number', 40)->unique();
            $table->string('loyalty_tier', 24)->default('Bronze');
            $table->integer('loyalty_points')->default(0);
            $table->decimal('lifetime_spend_aud', 14, 2)->default(0);
            $table->boolean('is_primary_golden')->default(true);
            $table->unsignedBigInteger('merged_into_profile_id')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('merged_into_profile_id')->references('id')->on('gourwest_customer_profiles')->onDelete('set null');

            $table->index(['region_code', 'loyalty_tier'], 'gw_profiles_region_tier_idx');
            $table->index(['golden_record_uid', 'is_primary_golden'], 'gw_profiles_golden_primary_idx');
            $table->index(['owner_user_id', 'is_primary_golden'], 'gw_profiles_owner_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gourwest_customer_profiles');
    }
};
