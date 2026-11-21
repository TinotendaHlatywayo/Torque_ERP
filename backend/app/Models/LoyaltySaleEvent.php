<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltySaleEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'sale_id',
        'event_key',
        'dedupe_hash',
        'source',
        'source_ref',
        'total_minor',
        'occurred_at',
    ];

    protected $casts = [
        'total_minor' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
