<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFitment extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'product_id',
        'make',
        'model',
        'year_from',
        'year_to',
        'engine',
        'source',
        'source_ref',
        'verified',
        'notes',
        'import_batch_id',
        'created_by',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
