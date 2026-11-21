<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceJob extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ESTIMATED = 'estimated';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'branch_id',
        'job_number',
        'customer_id',
        'technician_id',
        'vehicle_make',
        'vehicle_model',
        'vehicle_registration',
        'vehicle_year',
        'vin',
        'complaint',
        'diagnosis',
        'status',
        'previous_status',
        'estimate_minor',
        'approved_at',
        'approved_by',
        'approval_reference',
        'approval_channel',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'sale_id',
        'created_by',
    ];

    protected $casts = [
        'estimate_minor' => 'integer',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ESTIMATED,
            self::STATUS_APPROVED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function labourLines(): HasMany
    {
        return $this->hasMany(JobLabourLine::class, 'job_id');
    }

    public function partLines(): HasMany
    {
        return $this->hasMany(JobPartLine::class, 'job_id');
    }
}
