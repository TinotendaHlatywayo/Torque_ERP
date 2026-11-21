<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('marketing_consent_at')->nullable()->after('is_active');
            $table->string('marketing_channel', 20)->nullable()->after('marketing_consent_at');
            $table->boolean('marketing_channel_active')->default(false)->after('marketing_channel');

            $table->index(['branch_id', 'marketing_consent_at'], 'cust_marketing_consent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('cust_marketing_consent_idx');
            $table->dropColumn(['marketing_channel_active', 'marketing_channel', 'marketing_consent_at']);
        });
    }
};
