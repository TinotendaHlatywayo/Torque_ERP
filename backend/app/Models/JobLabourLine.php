<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobLabourLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'job_id',
        'workshop_service_id',
        'description',
        'hours',
        'rate_minor',
        'amount_minor',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'rate_minor' => 'integer',
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

    public function workshopService(): BelongsTo
    {
        return $this->belongsTo(WorkshopService::class);
    }
}
