<?php

namespace App\Support;

use App\Models\User;
use App\Support\Pricing\CartPriceResult;
use App\Support\Pricing\PriceLine;
use App\Support\Pricing\PriceLineResult;
use Illuminate\Validation\ValidationException;

final class PriceCalculator
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ?User $approver = null,
        private readonly ?string $overrideReason = null,
    ) {}

    /**
     * @param  array<int, PriceLine>  $lines
     */
    public function calculateCart(array $lines, string $currencyCode): CartPriceResult
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'A cart must contain at least one line.',
            ]);
        }

        $results = [];
        $subtotal = 0;
        $discount = 0;
        $net = 0;
        $tax = 0;
        $total = 0;
        $requiresApproval = false;

        foreach ($lines as $line) {
            $result = $this->calculateLine($line);
            $results[] = $result;

            $subtotal += $result->subtotalMinor;
            $discount += $result->discountMinor;
            $net += $result->netMinor;
            $tax += $result->taxMinor;
            $total += $result->totalMinor;

            if ($result->requiresApproval) {
                $requiresApproval = true;
            }
        }

        if ($requiresApproval && $this->approver !== null && $this->overrideReason !== null && trim($this->overrideReason) !== '') {
            foreach ($results as $result) {
                if (! $result->requiresApproval) {
                    continue;
                }

                $this->audit->log(
                    'price.override.approved',
                    'price_override',
                    null,
                    [
                        'product_id' => $result->productId,
                        'unit_id' => $result->unitId,
                        'currency_code' => $result->currencyCode,
                        'unit_price_minor' => $result->unitPriceMinor,
                        'quantity' => $result->quantity,
                        'discount_percent' => $result->discountPercent,
                        'net_minor' => $result->netMinor,
                        'approval_reasons' => $result->approvalReasons,
                        'override_reason' => trim($this->overrideReason),
                    ],
                    null,
                    $this->approver,
                    $result->productId,
                );
            }
        }

        return new CartPriceResult(
            lines: $results,
            subtotalMinor: $subtotal,
            discountMinor: $discount,
            netMinor: $net,
            taxMinor: $tax,
            totalMinor: $total,
            currencyCode: $currencyCode,
            pricedAt: now()->toIso8601String(),
            requiresApproval: $requiresApproval,
        );
    }

    private function calculateLine(PriceLine $line): PriceLineResult
    {
        if (bccomp($line->quantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'A cart quantity must be greater than zero.',
            ]);
        }

        if ($line->discountPercent < 0 || $line->discountPercent > 100) {
            throw ValidationException::withMessages([
                'discount_percent' => 'Discount percent must be between 0 and 100.',
            ]);
        }

        if ($line->unitPriceMinor < 0 || $line->floorPriceMinor < 0) {
            throw ValidationException::withMessages([
                'price' => 'Prices cannot be negative.',
            ]);
        }

        $subtotalMinor = (int) Decimal::roundHalfUp(
            bcmul((string) $line->unitPriceMinor, $line->quantity, 4),
            0,
        );

        $discountMinor = (int) Decimal::roundHalfUp(
            bcdiv(bcmul((string) $subtotalMinor, (string) $line->discountPercent, 6), '100', 6),
            0,
        );

        $floorSubtotalMinor = (int) Decimal::roundHalfUp(
            bcmul((string) $line->floorPriceMinor, $line->quantity, 4),
            0,
        );

        $netMinor = $subtotalMinor - $discountMinor;
        $approvalReasons = [];

        if ($line->discountPercent > $this->allowedDiscountPercent() && $line->discountPercent > 0) {
            $approvalReasons[] = 'discount_exceeds_allowed_threshold';
        }

        if ($netMinor < $floorSubtotalMinor) {
            $netMinor = $floorSubtotalMinor;
            $discountMinor = $subtotalMinor - $netMinor;
            $approvalReasons[] = 'below_floor_price';
        }

        $taxMinor = (int) Decimal::roundHalfUp(
            bcdiv(bcmul((string) $netMinor, (string) $line->taxRatePercent, 6), '100', 6),
            0,
        );

        $totalMinor = $netMinor + $taxMinor;

        return new PriceLineResult(
            productId: $line->productId,
            unitId: $line->unitId,
            currencyCode: $line->currencyCode,
            unitPriceMinor: $line->unitPriceMinor,
            quantity: Decimal::trim(bcadd($line->quantity, '0', 4)),
            floorPriceMinor: $line->floorPriceMinor,
            discountPercent: $line->discountPercent,
            taxRatePercent: $line->taxRatePercent,
            subtotalMinor: $subtotalMinor,
            discountMinor: $discountMinor,
            floorSubtotalMinor: $floorSubtotalMinor,
            netMinor: $netMinor,
            taxMinor: $taxMinor,
            totalMinor: $totalMinor,
            requiresApproval: $approvalReasons !== [],
            approvalReasons: $approvalReasons,
        );
    }

    private function allowedDiscountPercent(): float
    {
        return (float) config('pricing.max_discount_percent_without_approval', 10);
    }
}
