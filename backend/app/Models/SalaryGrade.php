<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryGrade extends Model
{
    use HasUlids;

    public const BASIS_MONTHLY = 'monthly';

    public const BASIS_HOURLY = 'hourly';

    public const BASES = [self::BASIS_MONTHLY, self::BASIS_HOURLY];

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'pay_basis',
        'base_rate_minor',
        'overtime_multiplier',
        'overtime_hourly_rate_minor',
        'currency_code',
        'is_active',
    ];

    protected $casts = [
        'base_rate_minor' => 'integer',
        'overtime_multiplier' => 'decimal:2',
        'overtime_hourly_rate_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
