<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'transfer_number',
        'source_branch_id',
        'destination_branch_id',
        'product_id',
        'unit_id',
        'destination_product_id',
        'destination_unit_id',
        'quantity',
        'dispatched_quantity',
        'reason',
        'status',
        'created_by',
        'dispatched_by',
        'dispatched_at',
        'in_transit_by',
        'in_transit_at',
        'received_by',
        'received_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'source_movement_id',
        'destination_movement_id',
        'cancellation_movement_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'dispatched_quantity' => 'decimal:4',
        'dispatched_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_DISPATCHED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_RECEIVED,
            self::STATUS_CANCELLED,
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function destinationProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'destination_product_id');
    }

    public function destinationUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'destination_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function inTransitUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'in_transit_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function sourceMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'source_movement_id');
    }

    public function destinationMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'destination_movement_id');
    }

    public function cancellationMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'cancellation_movement_id');
    }
}
