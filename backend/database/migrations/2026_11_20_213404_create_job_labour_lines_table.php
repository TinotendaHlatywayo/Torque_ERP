<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_labour_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('job_id')->constrained('service_jobs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('workshop_service_id')->nullable()->constrained('workshop_services')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('description', 200);
            $table->decimal('hours', 8, 2);
            $table->unsignedBigInteger('rate_minor');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('job_id', 'jl_job_idx');
            $table->index(['branch_id', 'workshop_service_id'], 'jl_branch_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_labour_lines');
    }
};
