<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
        'marketing_consent_at',
        'marketing_channel',
        'marketing_channel_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'marketing_consent_at' => 'datetime',
        'marketing_channel_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class);
    }

    public function loyaltyQualification(): HasOne
    {
        return $this->hasOne(LoyaltyQualification::class);
    }

    public function loyaltySaleEvents(): HasMany
    {
        return $this->hasMany(LoyaltySaleEvent::class);
    }

    public function hasActiveMarketingChannel(): bool
    {
        return $this->marketing_consent_at !== null
            && $this->marketing_channel !== null
            && $this->marketing_channel !== ''
            && $this->marketing_channel_active
            && in_array($this->marketing_channel, config('loyalty.marketing_channels', []), true);
    }
}
