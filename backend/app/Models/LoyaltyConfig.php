<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyConfig extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'inactivity_days',
        'min_qualifying_minor',
        'min_qualifying_sales',
        'loyalty_discount_percent',
        'combined_approval_threshold_percent',
        'updated_by',
    ];

    protected $casts = [
        'inactivity_days' => 'integer',
        'min_qualifying_minor' => 'integer',
        'min_qualifying_sales' => 'integer',
        'loyalty_discount_percent' => 'float',
        'combined_approval_threshold_percent' => 'float',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
