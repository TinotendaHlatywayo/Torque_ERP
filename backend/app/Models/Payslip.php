<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'payroll_run_id',
        'employee_id',
        'salary_grade_id',
        'pay_basis',
        'base_rate_minor',
        'base_pay_minor',
        'overtime_minor',
        'allowances_minor',
        'adjustment_earnings_minor',
        'pre_tax_deductions_minor',
        'statutory_employee_minor',
        'loan_deductions_minor',
        'adjustment_deductions_minor',
        'gross_minor',
        'deductions_minor',
        'employer_statutory_minor',
        'net_minor',
        'period_working_days',
        'payable_working_days',
        'regular_minutes',
        'overtime_minutes',
        'breakdown',
    ];

    protected $casts = [
        'base_rate_minor' => 'integer',
        'base_pay_minor' => 'integer',
        'overtime_minor' => 'integer',
        'allowances_minor' => 'integer',
        'adjustment_earnings_minor' => 'integer',
        'pre_tax_deductions_minor' => 'integer',
        'statutory_employee_minor' => 'integer',
        'loan_deductions_minor' => 'integer',
        'adjustment_deductions_minor' => 'integer',
        'gross_minor' => 'integer',
        'deductions_minor' => 'integer',
        'employer_statutory_minor' => 'integer',
        'net_minor' => 'integer',
        'breakdown' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }

    public function loanRepayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }
}
