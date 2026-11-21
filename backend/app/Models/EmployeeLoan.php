<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLoan extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_SETTLED, self::STATUS_CANCELLED];

    protected $fillable = [
        'branch_id',
        'employee_id',
        'principal_minor',
        'balance_minor',
        'installment_minor',
        'status',
        'issued_date',
        'notes',
    ];

    protected $casts = [
        'principal_minor' => 'integer',
        'balance_minor' => 'integer',
        'installment_minor' => 'integer',
        'issued_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }
}
