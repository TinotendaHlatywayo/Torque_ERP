<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyQualification extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'currency_code',
        'qualifying_sale_count',
        'gross_sales_minor',
        'returned_minor',
        'net_qualifying_minor',
        'last_purchase_at',
        'qualifies',
        'is_inactive',
        'calculated_at',
    ];

    protected $casts = [
        'qualifying_sale_count' => 'integer',
        'gross_sales_minor' => 'integer',
        'returned_minor' => 'integer',
        'net_qualifying_minor' => 'integer',
        'last_purchase_at' => 'datetime',
        'qualifies' => 'boolean',
        'is_inactive' => 'boolean',
        'calculated_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
