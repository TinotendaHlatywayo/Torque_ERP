<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('leave_type_id')->constrained('leave_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_requested', 6, 2);
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['branch_id', 'status', 'start_date']);
            $table->index(['branch_id', 'employee_id', 'start_date']);
            $table->unique(['id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
