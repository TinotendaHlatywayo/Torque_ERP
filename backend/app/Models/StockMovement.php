<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockMovement extends Model
{
    use HasUlids;

    public const TYPE_OPENING = 'opening';

    public const TYPE_SALE = 'sale';

    public const TYPE_JOB_CONSUMPTION = 'job_consumption';

    public const TYPE_CORRECTION = 'correction';

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_REVERSAL = 'reversal';

    public const SOURCE_OPENING = 'opening';

    public const SOURCE_SALE = 'sale';

    public const SOURCE_JOB = 'job';

    public const SOURCE_STOCK_TRANSFER = 'stock_transfer';

    public const SOURCE_STOCKTAKE = 'stocktake';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_REVERSAL = 'reversal';

    public const OPENING_MARKER = 'opening';

    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id',
        'product_id',
        'unit_id',
        'movement_type',
        'source',
        'source_id',
        'quantity',
        'unit_quantity',
        'unit_conversion_factor',
        'balance_after',
        'reason',
        'idempotency_key',
        'request_hash',
        'opening_marker',
        'reverses_movement_id',
        'actor_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_quantity' => 'decimal:4',
        'unit_conversion_factor' => 'decimal:6',
        'balance_after' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_movement_id');
    }
}
