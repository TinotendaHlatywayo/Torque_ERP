<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use HasUlids;

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_READY = 'ready';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'branch_id', 'product_id', 'unit_id', 'reservation_number', 'product_name',
        'quantity', 'amount_minor', 'currency_code', 'customer_name', 'customer_email',
        'customer_phone', 'token_hash', 'idempotency_key', 'request_hash', 'status',
        'expires_at', 'confirmed_at', 'cancelled_at', 'expired_at', 'ready_at', 'picked_up_at',
        'actor_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'amount_minor' => 'integer',
        'customer_name' => 'encrypted',
        'customer_email' => 'encrypted',
        'customer_phone' => 'encrypted',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
        'ready_at' => 'datetime',
        'picked_up_at' => 'datetime',
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

    public function payment(): HasOne
    {
        return $this->hasOne(ReservationPayment::class);
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(ReservationNotificationDelivery::class);
    }
}
