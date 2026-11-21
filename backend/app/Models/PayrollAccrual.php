<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAccrual extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'payroll_run_id',
        'expense_id',
        'accrued_minor',
        'net_pay_minor',
        'currency_code',
        'incurred_date',
        'actor_id',
    ];

    protected $casts = [
        'accrued_minor' => 'integer',
        'net_pay_minor' => 'integer',
        'incurred_date' => 'date',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
