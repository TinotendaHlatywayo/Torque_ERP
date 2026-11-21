<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollStatutoryRule extends Model
{
    use HasUlids;

    public const APPLIES_GROSS = 'gross';

    public const APPLIES_BASIC = 'basic';

    public const BASES = [self::APPLIES_GROSS, self::APPLIES_BASIC];

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'applies_to',
        'employee_rate',
        'employer_rate',
        'fixed_employee_minor',
        'fixed_employer_minor',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'employee_rate' => 'decimal:4',
        'employer_rate' => 'decimal:4',
        'fixed_employee_minor' => 'integer',
        'fixed_employer_minor' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
