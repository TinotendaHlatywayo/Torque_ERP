<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenance extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'asset_id',
        'performed_date',
        'cost_minor',
        'currency_code',
        'provider',
        'description',
        'expense_id',
        'payment_id',
        'actor_id',
    ];

    protected $casts = [
        'cost_minor' => 'integer',
        'performed_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(MoneyMovement::class, 'payment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
