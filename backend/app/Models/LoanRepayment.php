<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepayment extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id',
        'employee_loan_id',
        'payslip_id',
        'payroll_run_id',
        'amount_minor',
        'balance_before_minor',
        'balance_after_minor',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'balance_before_minor' => 'integer',
        'balance_after_minor' => 'integer',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
