<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_oem_refs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('oem_number', 120);
            $table->string('brand', 120)->nullable();
            $table->string('source', 200);
            $table->string('source_ref', 200);
            $table->string('verified', 20)->default('declared');
            $table->text('notes')->nullable();
            $table->string('import_batch_id', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'product_id', 'oem_number', 'source_ref'], 'por_unique_oem');
            $table->index(['branch_id', 'oem_number'], 'por_branch_oem_idx');
            $table->index(['branch_id', 'source', 'source_ref'], 'por_branch_source_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_oem_refs');
    }
};
