<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_part_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('job_id')->constrained('service_jobs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('amount_minor');
            $table->string('idempotency_key', 100)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->ulid('stock_movement_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['job_id', 'idempotency_key'], 'jp_job_idem_uniq');
            $table->unique('stock_movement_id', 'jp_stock_movement_uniq');
            $table->index('job_id', 'jp_job_idx');
            $table->index(['branch_id', 'product_id'], 'jp_branch_product_idx');

            $table->foreign(['product_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign(['unit_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('stock_movement_id')
                ->references('id')
                ->on('stock_movements')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_part_lines');
    }
};
