<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollRun extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const KIND_REGULAR = 'regular';

    public const KIND_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'branch_id',
        'payroll_period_id',
        'kind',
        'version',
        'status',
        'gross_minor',
        'deductions_minor',
        'employer_statutory_minor',
        'net_minor',
        'warnings',
        'generated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'gross_minor' => 'integer',
        'deductions_minor' => 'integer',
        'employer_statutory_minor' => 'integer',
        'net_minor' => 'integer',
        'warnings' => 'array',
        'approved_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function accrual(): HasOne
    {
        return $this->hasOne(PayrollAccrual::class);
    }
}
