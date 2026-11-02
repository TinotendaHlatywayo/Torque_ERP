<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxRate extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'from_currency',
        'to_currency',
        'rate',
        'source',
        'effective_at',
        'rounding_mode',
        'entered_by',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'effective_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
