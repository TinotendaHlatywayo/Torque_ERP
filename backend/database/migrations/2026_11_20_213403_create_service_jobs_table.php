<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('job_number', 40);
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('vehicle_make', 120);
            $table->string('vehicle_model', 160);
            $table->string('vehicle_registration', 60)->nullable();
            $table->string('vehicle_year', 12)->nullable();
            $table->string('vin', 80)->nullable();
            $table->text('complaint');
            $table->text('diagnosis')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('previous_status', 20)->nullable();
            $table->unsignedBigInteger('estimate_minor')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('approval_reference', 120)->nullable();
            $table->string('approval_channel', 60)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUlid('sale_id')->nullable()->constrained('sales')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'job_number'], 'sj_branch_number_uniq');
            $table->index(['branch_id', 'status', 'created_at'], 'sj_branch_status_idx');
            $table->index(['branch_id', 'customer_id'], 'sj_branch_customer_idx');
            $table->unique('sale_id', 'sj_sale_uniq');

            $table->foreign(['customer_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('customers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
    }
};
