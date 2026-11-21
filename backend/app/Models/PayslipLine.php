<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipLine extends Model
{
    use HasUlids;

    public const CATEGORY_EARNING = 'earning';

    public const CATEGORY_DEDUCTION = 'deduction';

    public const CATEGORY_STATUTORY_EMPLOYEE = 'statutory_employee';

    public const CATEGORY_STATUTORY_EMPLOYER = 'statutory_employer';

    public const CATEGORY_LOAN = 'loan';

    public const UPDATED_AT = null;

    protected $fillable = [
        'payslip_id',
        'category',
        'code',
        'label',
        'amount_minor',
        'units',
        'rate_minor',
        'source_type',
        'source_id',
        'metadata',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'units' => 'decimal:2',
        'rate_minor' => 'integer',
        'metadata' => 'array',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
