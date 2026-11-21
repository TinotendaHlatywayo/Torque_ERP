<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPartLine extends Model
{
    use HasUlids;

    public bool $idempotentReplay = false;

    protected $fillable = [
        'branch_id',
        'job_id',
        'product_id',
        'unit_id',
        'quantity',
        'unit_price_minor',
        'amount_minor',
        'idempotency_key',
        'request_hash',
        'stock_movement_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price_minor' => 'integer',
        'amount_minor' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
