<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_sale_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches', 'id', 'lse_branch_fk')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('customer_id');
            $table->ulid('sale_id')->nullable();
            $table->string('event_key', 120);
            $table->string('dedupe_hash', 64);
            $table->string('source', 20)->default('sale');
            $table->string('source_ref', 120)->nullable();
            $table->unsignedBigInteger('total_minor');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'event_key'], 'lse_branch_event_uniq');
            $table->unique(['branch_id', 'dedupe_hash'], 'lse_branch_dedupe_uniq');
            $table->unique(['id', 'branch_id'], 'lse_id_branch_uniq');
            $table->index(['branch_id', 'customer_id', 'occurred_at'], 'lse_customer_occurred_idx');

            $table->foreign(['customer_id', 'branch_id'], 'lse_customer_branch_fk')
                ->references(['id', 'branch_id'])
                ->on('customers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign(['sale_id', 'branch_id'], 'lse_sale_branch_fk')
                ->references(['id', 'branch_id'])
                ->on('sales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_sale_events');
    }
};
