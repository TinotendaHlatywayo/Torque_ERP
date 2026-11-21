<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends Model
{
    use HasUlids;

    public const STATUS_RECORDED = 'recorded';

    protected $fillable = [
        'branch_id',
        'payroll_run_id',
        'payment_account_id',
        'money_movement_id',
        'amount_minor',
        'currency_code',
        'paid_at',
        'method',
        'reference',
        'status',
        'idempotency_key',
        'request_hash',
        'actor_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function moneyMovement(): BelongsTo
    {
        return $this->belongsTo(MoneyMovement::class, 'money_movement_id');
    }
}
