<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'category_id',
        'base_unit_id',
        'sku',
        'name',
        'description',
        'barcode',
        'selling_price_minor',
        'currency_code',
        'track_stock',
        'is_active',
        'public_slug',
        'public_description',
        'is_public',
        'public_image_id',
        'public_image_url',
        'public_image_alt',
    ];

    protected $casts = [
        'selling_price_minor' => 'integer',
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function stockLevel(): HasOne
    {
        return $this->hasOne(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function publicImage(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'public_image_id');
    }

    public function fitments(): HasMany
    {
        return $this->hasMany(ProductFitment::class);
    }

    public function oemRefs(): HasMany
    {
        return $this->hasMany(ProductOemRef::class);
    }
}
