<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('transfer_number', 40);
            $table->foreignUlid('source_branch_id')->constrained('branches', 'id', 'st_source_branch_fk')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('destination_branch_id')->constrained('branches', 'id', 'st_dest_branch_fk')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('product_id');
            $table->ulid('unit_id');
            $table->ulid('destination_product_id');
            $table->ulid('destination_unit_id');
            $table->decimal('quantity', 18, 4);
            $table->decimal('dispatched_quantity', 18, 4)->nullable();
            $table->string('reason', 500);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'st_created_by_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users', 'id', 'st_dispatched_by_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('in_transit_by')->nullable()->constrained('users', 'id', 'st_in_transit_by_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('in_transit_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users', 'id', 'st_received_by_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users', 'id', 'st_cancelled_by_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->ulid('source_movement_id')->nullable();
            $table->ulid('destination_movement_id')->nullable();
            $table->ulid('cancellation_movement_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['source_branch_id', 'transfer_number'], 'st_branch_number_uniq');
            $table->index(['source_branch_id', 'status', 'created_at'], 'st_source_status_idx');
            $table->index(['destination_branch_id', 'status'], 'st_dest_status_idx');

            $table->foreign(['product_id', 'source_branch_id'], 'st_product_branch_fk')
                ->references(['id', 'branch_id'])
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign(['unit_id', 'source_branch_id'], 'st_unit_branch_fk')
                ->references(['id', 'branch_id'])
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign(['destination_product_id', 'destination_branch_id'], 'st_dest_product_branch_fk')
                ->references(['id', 'branch_id'])
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign(['destination_unit_id', 'destination_branch_id'], 'st_dest_unit_branch_fk')
                ->references(['id', 'branch_id'])
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('source_movement_id', 'st_source_movement_fk')->references('id')->on('stock_movements')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('destination_movement_id', 'st_dest_movement_fk')->references('id')->on('stock_movements')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('cancellation_movement_id', 'st_cancel_movement_fk')->references('id')->on('stock_movements')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
