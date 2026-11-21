<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\LoyaltyQualification;
use App\Models\User;
use App\Support\Pricing\PriceLine;
use Illuminate\Validation\ValidationException;

final class LoyaltyStackingService
{
    public function __construct(
        private readonly LoyaltyConfigService $configs,
        private readonly PriceCalculatorFactory $priceCalculatorFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function quote(Branch $branch, User $actor, bool $canApprove, array $data): array
    {
        $config = $this->configs->forBranch($branch);
        $qualification = LoyaltyQualification::where('branch_id', $branch->id)
            ->where('customer_id', (string) $data['customer_id'])
            ->firstOrFail();

        if (! $qualification->qualifies) {
            throw ValidationException::withMessages([
                'customer_id' => 'The customer does not currently qualify for a loyalty discount.',
            ]);
        }

        $loyaltyDiscountPercent = (float) $config->loyalty_discount_percent;
        $manualDiscountPercent = (float) $data['manual_discount_percent'];
        $combinedDiscountPercent = min(100.0, $loyaltyDiscountPercent + $manualDiscountPercent);
        $thresholdPercent = (float) $config->combined_approval_threshold_percent;
        $exceedsThreshold = $combinedDiscountPercent > $thresholdPercent;
        $approvalReason = isset($data['approval_reason']) && is_string($data['approval_reason'])
            ? trim($data['approval_reason'])
            : '';

        $line = new PriceLine(
            productId: isset($data['product_id']) && is_string($data['product_id']) ? $data['product_id'] : 'loyalty-quote',
            unitId: isset($data['unit_id']) && is_string($data['unit_id']) ? $data['unit_id'] : 'loyalty-quote',
            currencyCode: $branch->currency_code,
            unitPriceMinor: (int) $data['unit_price_minor'],
            quantity: (string) $data['quantity'],
            floorPriceMinor: (int) ($data['floor_price_minor'] ?? 0),
            discountPercent: $combinedDiscountPercent,
            taxRatePercent: (float) ($data['tax_rate_percent'] ?? 0),
        );

        $lineSubtotalMinor = (int) Decimal::roundHalfUp(
            bcmul((string) $line->unitPriceMinor, $line->quantity, 4),
            0,
        );
        $rawDiscountMinor = (int) Decimal::roundHalfUp(
            bcdiv(bcmul((string) $lineSubtotalMinor, (string) $combinedDiscountPercent, 6), '100', 6),
            0,
        );
        $floorSubtotalMinor = (int) Decimal::roundHalfUp(
            bcmul((string) $line->floorPriceMinor, $line->quantity, 4),
            0,
        );
        $belowFloor = ($lineSubtotalMinor - $rawDiscountMinor) < $floorSubtotalMinor;
        $requiresApproval = $exceedsThreshold || $belowFloor;

        if ($requiresApproval && (! $canApprove || $approvalReason === '')) {
            throw ValidationException::withMessages([
                'approval_reason' => 'The combined loyalty and manual discount exceeds the configured threshold and requires approval from an authorized approver.',
            ]);
        }

        $calculator = $this->priceCalculatorFactory->make(
            $requiresApproval ? $actor : null,
            $requiresApproval ? $approvalReason : null,
        );
        $result = $calculator->calculateCart([$line], $branch->currency_code)->lines[0];

        $approvalReasons = $result->approvalReasons;

        if ($exceedsThreshold) {
            $approvalReasons[] = 'combined_discount_exceeds_threshold';
        }

        return [
            'customer_id' => $qualification->customer_id,
            'loyalty_discount_percent' => $loyaltyDiscountPercent,
            'manual_discount_percent' => $manualDiscountPercent,
            'combined_discount_percent' => $combinedDiscountPercent,
            'approval_threshold_percent' => $thresholdPercent,
            'subtotal_minor' => $result->subtotalMinor,
            'floor_subtotal_minor' => $result->floorSubtotalMinor,
            'net_minor' => $result->netMinor,
            'tax_minor' => $result->taxMinor,
            'total_minor' => $result->totalMinor,
            'floor_price_applied' => in_array('below_floor_price', $result->approvalReasons, true),
            'requires_approval' => $requiresApproval || $result->requiresApproval,
            'approved' => $requiresApproval && $canApprove && $approvalReason !== '',
            'approval_reason' => $approvalReason !== '' ? $approvalReason : null,
            'approval_reasons' => array_values(array_unique($approvalReasons)),
            'currency_code' => $branch->currency_code,
        ];
    }
}
