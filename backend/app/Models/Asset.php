<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISPOSED = 'disposed';

    public const SOURCE_EXPENSE = 'expense';

    public const SOURCE_SUPPLIER_BILL = 'supplier_bill';

    public const SOURCE_MONEY_MOVEMENT = 'money_movement';

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'acquisition_date',
        'cost_minor',
        'currency_code',
        'location',
        'custodian',
        'status',
        'source_type',
        'source_id',
        'disposal_date',
        'disposal_proceeds_minor',
        'disposal_notes',
        'actor_id',
    ];

    protected $casts = [
        'cost_minor' => 'integer',
        'disposal_proceeds_minor' => 'integer',
        'acquisition_date' => 'date',
        'disposal_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }
}
