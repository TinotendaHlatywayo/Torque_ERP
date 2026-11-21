<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('stocktake_number', 40);
            $table->foreignUlid('branch_id')->constrained('branches', 'id', 'stk_branch_fk')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('reason', 500);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'stk_created_by_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id', 'stk_reviewed_by_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users', 'id', 'stk_approved_by_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_reference', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'stocktake_number'], 'stk_branch_number_uniq');
            $table->index(['branch_id', 'status', 'created_at'], 'stk_branch_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktakes');
    }
};
