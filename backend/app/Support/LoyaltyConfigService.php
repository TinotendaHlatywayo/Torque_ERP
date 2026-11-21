<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\LoyaltyConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LoyaltyConfigService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function forBranch(Branch $branch): LoyaltyConfig
    {
        return LoyaltyConfig::firstOrCreate(
            ['branch_id' => $branch->id],
            [
                'inactivity_days' => config('loyalty.inactivity_days'),
                'min_qualifying_minor' => config('loyalty.min_qualifying_minor'),
                'min_qualifying_sales' => config('loyalty.min_qualifying_sales'),
                'loyalty_discount_percent' => config('loyalty.loyalty_discount_percent'),
                'combined_approval_threshold_percent' => config('loyalty.combined_approval_threshold_percent'),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data, User $actor): LoyaltyConfig
    {
        return DB::transaction(function () use ($branch, $data, $actor): LoyaltyConfig {
            $config = $this->forBranch($branch);

            $before = $config->only([
                'inactivity_days',
                'min_qualifying_minor',
                'min_qualifying_sales',
                'loyalty_discount_percent',
                'combined_approval_threshold_percent',
            ]);

            $config->update([
                'inactivity_days' => (int) $data['inactivity_days'],
                'min_qualifying_minor' => (int) $data['min_qualifying_minor'],
                'min_qualifying_sales' => (int) $data['min_qualifying_sales'],
                'loyalty_discount_percent' => (float) $data['loyalty_discount_percent'],
                'combined_approval_threshold_percent' => (float) $data['combined_approval_threshold_percent'],
                'updated_by' => $actor->id,
            ]);

            $this->audit->log(
                'loyalty.config.updated',
                $config->refresh(),
                $before,
                $config->only(array_keys($before)),
                $branch->id,
                $actor,
            );

            return $config;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(LoyaltyConfig $config): array
    {
        return [
            'inactivity_days' => $config->inactivity_days,
            'min_qualifying_minor' => $config->min_qualifying_minor,
            'min_qualifying_sales' => $config->min_qualifying_sales,
            'loyalty_discount_percent' => $config->loyalty_discount_percent,
            'combined_approval_threshold_percent' => $config->combined_approval_threshold_percent,
            'marketing_channels' => config('loyalty.marketing_channels', []),
            'channel_integration' => 'not_configured',
            'updated_at' => $config->updated_at?->toIso8601String(),
        ];
    }
}
