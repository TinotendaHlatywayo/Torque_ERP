<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'sale_id',
        'method',
        'amount_minor',
        'currency_code',
        'reference',
        'status',
        'idempotency_key',
        'request_hash',
        'actor_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'created_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
