<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopService extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'description',
        'default_hours',
        'default_rate_minor',
        'currency_code',
        'is_active',
    ];

    protected $casts = [
        'default_hours' => 'decimal:2',
        'default_rate_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
