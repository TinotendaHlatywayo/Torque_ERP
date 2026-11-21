<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdjustment extends Model
{
    use HasUlids;

    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPES = [self::TYPE_EARNING, self::TYPE_DEDUCTION];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPLIED, self::STATUS_REVERSED];

    protected $fillable = [
        'branch_id',
        'employee_id',
        'source_payslip_id',
        'type',
        'label',
        'amount_minor',
        'reason',
        'status',
        'applied_payslip_id',
        'applied_run_id',
        'reverses_adjustment_id',
        'applied_at',
        'reversed_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'applied_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function sourcePayslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'source_payslip_id');
    }

    public function appliedPayslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'applied_payslip_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_adjustment_id');
    }
}
