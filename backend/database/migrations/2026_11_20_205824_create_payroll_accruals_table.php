<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_accruals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('payroll_run_id')->constrained('payroll_runs')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('expense_id')->constrained('expenses')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('accrued_minor');
            $table->unsignedBigInteger('net_pay_minor');
            $table->string('currency_code', 3);
            $table->date('incurred_date');
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('payroll_run_id');
            $table->unique(['id', 'branch_id']);
            $table->index(['branch_id', 'incurred_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_accruals');
    }
};
