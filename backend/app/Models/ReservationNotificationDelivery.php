<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationNotificationDelivery extends Model
{
    use HasUlids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const RECIPIENT_CUSTOMER = 'customer';

    public const RECIPIENT_BRANCH_USER = 'branch_user';

    protected $fillable = [
        'reservation_id', 'branch_id', 'user_id', 'event', 'recipient_type', 'recipient_key', 'status',
        'attempts', 'sent_at', 'failed_at', 'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
