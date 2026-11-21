<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_grades', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 30);
            $table->string('pay_basis', 20);
            $table->unsignedBigInteger('base_rate_minor');
            $table->decimal('overtime_multiplier', 4, 2)->default(1.50);
            $table->unsignedBigInteger('overtime_hourly_rate_minor')->nullable();
            $table->char('currency_code', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'is_active', 'name']);
            $table->unique(['id', 'branch_id']);
        });

        Schema::create('employee_salary_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('salary_grade_id')->constrained('salary_grades')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['branch_id', 'employee_id', 'effective_from'], 'employee_salary_assignments_branch_employee_effective_index');
            $table->unique(['id', 'branch_id']);
        });

        Schema::create('employee_pay_components', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('code', 30);
            $table->string('label', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['branch_id', 'employee_id', 'type', 'is_active'], 'employee_pay_components_branch_employee_type_active_index');
            $table->unique(['id', 'branch_id']);
        });

        Schema::create('payroll_statutory_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 30);
            $table->string('applies_to', 20)->default('gross');
            $table->decimal('employee_rate', 7, 4)->default(0);
            $table->decimal('employer_rate', 7, 4)->default(0);
            $table->unsignedBigInteger('fixed_employee_minor')->default(0);
            $table->unsignedBigInteger('fixed_employer_minor')->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'is_active', 'effective_from'], 'payroll_statutory_rules_branch_active_effective_index');
            $table->unique(['id', 'branch_id']);
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('principal_minor');
            $table->unsignedBigInteger('balance_minor');
            $table->unsignedBigInteger('installment_minor');
            $table->string('status', 20)->default('active');
            $table->date('issued_date');
            $table->string('notes', 2000)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['branch_id', 'employee_id', 'status']);
            $table->unique(['id', 'branch_id']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date');
            $table->string('status', 20)->default('open');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'start_date', 'end_date']);
            $table->index(['branch_id', 'status', 'pay_date']);
            $table->unique(['id', 'branch_id']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('payroll_period_id')->constrained('payroll_periods')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('kind', 20)->default('regular');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('gross_minor')->default(0);
            $table->unsignedBigInteger('deductions_minor')->default(0);
            $table->unsignedBigInteger('employer_statutory_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->json('warnings')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['payroll_period_id', 'version']);
            $table->index(['branch_id', 'status', 'created_at']);
            $table->unique(['id', 'branch_id']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('payroll_run_id')->constrained('payroll_runs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('salary_grade_id')->nullable()->constrained('salary_grades')->cascadeOnUpdate()->nullOnDelete();
            $table->string('pay_basis', 20);
            $table->unsignedBigInteger('base_rate_minor');
            $table->unsignedBigInteger('base_pay_minor');
            $table->unsignedBigInteger('overtime_minor')->default(0);
            $table->unsignedBigInteger('allowances_minor')->default(0);
            $table->unsignedBigInteger('adjustment_earnings_minor')->default(0);
            $table->unsignedBigInteger('pre_tax_deductions_minor')->default(0);
            $table->unsignedBigInteger('statutory_employee_minor')->default(0);
            $table->unsignedBigInteger('loan_deductions_minor')->default(0);
            $table->unsignedBigInteger('adjustment_deductions_minor')->default(0);
            $table->unsignedBigInteger('gross_minor');
            $table->unsignedBigInteger('deductions_minor');
            $table->unsignedBigInteger('employer_statutory_minor')->default(0);
            $table->bigInteger('net_minor');
            $table->unsignedInteger('period_working_days')->default(0);
            $table->unsignedInteger('payable_working_days')->default(0);
            $table->unsignedInteger('regular_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['branch_id', 'employee_id']);
            $table->unique(['id', 'branch_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payslip_id')->constrained('payslips')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('code', 30);
            $table->string('label', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->decimal('units', 10, 2)->nullable();
            $table->unsignedBigInteger('rate_minor')->nullable();
            $table->string('source_type', 50)->nullable();
            $table->string('source_id', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payslip_id', 'category']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('source_payslip_id')->nullable()->constrained('payslips')->cascadeOnUpdate()->nullOnDelete();
            $table->string('type', 20);
            $table->string('label', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 2000);
            $table->string('status', 20)->default('pending');
            $table->foreignUlid('applied_payslip_id')->nullable()->constrained('payslips')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignUlid('applied_run_id')->nullable()->constrained('payroll_runs')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignUlid('reverses_adjustment_id')->nullable()->constrained('payroll_adjustments')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['branch_id', 'employee_id', 'status']);
            $table->unique(['id', 'branch_id']);
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('employee_loan_id')->constrained('employee_loans')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('payslip_id')->constrained('payslips')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('payroll_run_id')->constrained('payroll_runs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('balance_before_minor');
            $table->unsignedBigInteger('balance_after_minor');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['employee_loan_id', 'payslip_id']);
            $table->index(['branch_id', 'payroll_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('payroll_statutory_rules');
        Schema::dropIfExists('employee_pay_components');
        Schema::dropIfExists('employee_salary_assignments');
        Schema::dropIfExists('salary_grades');
    }
};
