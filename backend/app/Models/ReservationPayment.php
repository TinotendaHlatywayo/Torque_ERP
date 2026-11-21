<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationPayment extends Model
{
    use HasUlids;

    protected $fillable = [
        'reservation_id', 'provider', 'merchant_reference', 'provider_reference', 'amount_minor',
        'currency_code', 'status', 'poll_url', 'redirect_url', 'provider_status', 'paid_at', 'last_event_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'poll_url' => 'encrypted',
        'redirect_url' => 'encrypted',
        'paid_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
