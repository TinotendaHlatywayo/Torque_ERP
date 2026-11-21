<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktake_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('stocktake_id')->constrained('stocktakes', 'id', 'stkl_stocktake_fk')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained('branches', 'id', 'stkl_branch_fk')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('product_id');
            $table->ulid('unit_id');
            $table->decimal('expected_quantity', 18, 4);
            $table->decimal('counted_quantity', 18, 4);
            $table->decimal('variance', 18, 4)->nullable();
            $table->string('reason', 500);
            $table->ulid('stock_movement_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['stocktake_id', 'product_id'], 'stkl_stocktake_product_uniq');
            $table->index(['branch_id', 'product_id'], 'stkl_branch_product_idx');

            $table->foreign(['product_id', 'branch_id'], 'stkl_product_branch_fk')
                ->references(['id', 'branch_id'])
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign(['unit_id', 'branch_id'], 'stkl_unit_branch_fk')
                ->references(['id', 'branch_id'])
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('stock_movement_id', 'stkl_movement_fk')->references('id')->on('stock_movements')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_lines');
    }
};
