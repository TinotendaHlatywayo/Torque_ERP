<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayComponent extends Model
{
    use HasUlids;

    public const TYPE_ALLOWANCE = 'allowance';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPES = [self::TYPE_ALLOWANCE, self::TYPE_DEDUCTION];

    protected $fillable = [
        'branch_id',
        'employee_id',
        'type',
        'code',
        'label',
        'amount_minor',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
