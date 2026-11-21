<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_fitments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('make', 120);
            $table->string('model', 160);
            $table->string('year_from', 12)->nullable();
            $table->string('year_to', 12)->nullable();
            $table->string('engine', 160)->nullable();
            $table->string('source', 200);
            $table->string('source_ref', 200);
            $table->string('verified', 20)->default('declared');
            $table->text('notes')->nullable();
            $table->string('import_batch_id', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'product_id', 'make', 'model', 'year_from', 'year_to', 'engine', 'source_ref'], 'pf_unique_fit');
            $table->index(['branch_id', 'make', 'model'], 'pf_branch_make_model_idx');
            $table->index(['branch_id', 'source', 'source_ref'], 'pf_branch_source_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_fitments');
    }
};
