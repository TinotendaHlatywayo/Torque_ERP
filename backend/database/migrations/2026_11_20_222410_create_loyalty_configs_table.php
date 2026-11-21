<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_configs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches', 'id', 'lc_branch_fk')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('inactivity_days')->default(config('loyalty.inactivity_days'));
            $table->unsignedBigInteger('min_qualifying_minor')->default(config('loyalty.min_qualifying_minor'));
            $table->unsignedSmallInteger('min_qualifying_sales')->default(config('loyalty.min_qualifying_sales'));
            $table->decimal('loyalty_discount_percent', 5, 2)->default(config('loyalty.loyalty_discount_percent'));
            $table->decimal('combined_approval_threshold_percent', 5, 2)->default(config('loyalty.combined_approval_threshold_percent'));
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id', 'lc_updated_by_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('branch_id', 'lc_branch_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_configs');
    }
};
