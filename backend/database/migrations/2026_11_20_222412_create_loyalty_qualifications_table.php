<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_qualifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches', 'id', 'lq_branch_fk')->cascadeOnUpdate()->cascadeOnDelete();
            $table->ulid('customer_id');
            $table->string('currency_code', 3);
            $table->unsignedInteger('qualifying_sale_count')->default(0);
            $table->unsignedBigInteger('gross_sales_minor')->default(0);
            $table->unsignedBigInteger('returned_minor')->default(0);
            $table->unsignedBigInteger('net_qualifying_minor')->default(0);
            $table->timestamp('last_purchase_at')->nullable();
            $table->boolean('qualifies')->default(false);
            $table->boolean('is_inactive')->default(false);
            $table->timestamp('calculated_at');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'customer_id'], 'lq_branch_customer_uniq');
            $table->unique(['id', 'branch_id'], 'lq_id_branch_uniq');
            $table->index(['branch_id', 'qualifies', 'is_inactive'], 'lq_qualification_idx');

            $table->foreign(['customer_id', 'branch_id'], 'lq_customer_branch_fk')
                ->references(['id', 'branch_id'])
                ->on('customers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_qualifications');
    }
};
