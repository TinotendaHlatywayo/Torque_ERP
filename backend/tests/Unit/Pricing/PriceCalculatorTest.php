<?php

namespace Tests\Unit\Pricing;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PriceCalculator;
use App\Support\Pricing\PriceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_a_basic_line_and_cart_total(): void
    {
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 1250, '2'),
        ], 'USD');

        $this->assertSame(2500, $result->subtotalMinor);
        $this->assertSame(0, $result->discountMinor);
        $this->assertSame(0, $result->taxMinor);
        $this->assertSame(2500, $result->totalMinor);
        $this->assertFalse($result->requiresApproval);
        $this->assertSame(2500, $result->lines[0]->subtotalMinor);
        $this->assertSame(2500, $result->lines[0]->netMinor);
        $this->assertSame(2500, $result->lines[0]->totalMinor);
    }

    public function test_decimal_quantities_are_rounded_to_minor_units(): void
    {
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 333, '2.5'),
        ], 'USD');

        $this->assertSame(833, $result->totalMinor);
        $this->assertSame('2.5', $result->lines[0]->quantity);
    }

    public function test_discount_within_threshold_does_not_require_approval(): void
    {
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 1000, '1', discountPercent: 10),
        ], 'USD');

        $this->assertSame(1000, $result->subtotalMinor);
        $this->assertSame(100, $result->discountMinor);
        $this->assertSame(900, $result->netMinor);
        $this->assertSame(900, $result->totalMinor);
        $this->assertFalse($result->requiresApproval);
        $this->assertSame([], $result->lines[0]->approvalReasons);
    }

    public function test_discount_above_threshold_requires_approval(): void
    {
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 1000, '1', discountPercent: 15),
        ], 'USD');

        $this->assertSame(150, $result->discountMinor);
        $this->assertSame(850, $result->netMinor);
        $this->assertTrue($result->requiresApproval);
        $this->assertSame(['discount_exceeds_allowed_threshold'], $result->lines[0]->approvalReasons);
    }

    public function test_floor_price_is_enforced_and_requires_approval(): void
    {
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 1000, '2', floorPriceMinor: 900, discountPercent: 20),
        ], 'USD');

        $this->assertSame(2000, $result->subtotalMinor);
        $this->assertSame(1800, $result->lines[0]->floorSubtotalMinor);
        $this->assertSame(1800, $result->lines[0]->netMinor);
        $this->assertTrue($result->requiresApproval);
        $this->assertContains('below_floor_price', $result->lines[0]->approvalReasons);
    }

    public function test_floor_clamp_keeps_discount_and_cart_net_consistent(): void
    {
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 1000, '2', floorPriceMinor: 900, discountPercent: 20),
            new PriceLine('product-2', 'unit-2', 'USD', 500, '1'),
        ], 'USD');

        $this->assertSame(200, $result->lines[0]->discountMinor);
        $this->assertSame(1800, $result->lines[0]->netMinor);
        $this->assertSame(2500, $result->subtotalMinor);
        $this->assertSame(200, $result->discountMinor);
        $this->assertSame(2300, $result->netMinor);
        $this->assertSame($result->subtotalMinor - $result->discountMinor, $result->netMinor);
        $this->assertSame($result->lines[0]->netMinor + $result->lines[1]->netMinor, $result->netMinor);
    }

    public function test_tax_hook_is_applied_after_net_price(): void
    {
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 1000, '1', taxRatePercent: 15),
        ], 'USD');

        $this->assertSame(1000, $result->netMinor);
        $this->assertSame(150, $result->taxMinor);
        $this->assertSame(1150, $result->totalMinor);
    }

    public function test_cart_totals_combine_multiple_lines(): void
    {
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 1000, '1', discountPercent: 5, taxRatePercent: 10),
            new PriceLine('product-2', 'unit-2', 'USD', 500, '3'),
        ], 'USD');

        $this->assertSame(2500, $result->subtotalMinor);
        $this->assertSame(50, $result->discountMinor);
        $this->assertSame(95, $result->taxMinor);
        $this->assertSame(2545, $result->totalMinor);
        $this->assertCount(2, $result->lines);
    }

    public function test_override_is_audited_with_actor_and_reason(): void
    {
        $approver = User::factory()->create(['name' => 'Manager']);
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class), $approver, 'Approved 15% discount');

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 1000, '1', discountPercent: 15),
        ], 'USD');

        $this->assertTrue($result->requiresApproval);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'price.override.approved',
            'entity_type' => 'price_override',
            'entity_id' => 'product-1',
            'user_id' => $approver->id,
        ]);
    }

    public function test_threshold_is_configured_not_hard_coded(): void
    {
        config(['pricing.max_discount_percent_without_approval' => 5]);

        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        $result = $calculator->calculateCart([
            new PriceLine('product-1', 'unit-1', 'USD', 1000, '1', discountPercent: 7),
        ], 'USD');

        $this->assertTrue($result->requiresApproval);
        $this->assertSame(['discount_exceeds_allowed_threshold'], $result->lines[0]->approvalReasons);
    }

    public function test_invalid_cart_inputs_are_rejected(): void
    {
        $calculator = new PriceCalculator($this->app->make(AuditLogger::class));

        try {
            $calculator->calculateCart([], 'USD');
            $this->fail('Expected an empty cart to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }

        try {
            $calculator->calculateCart([
                new PriceLine('product-1', 'unit-1', 'USD', 1000, '0'),
            ], 'USD');
            $this->fail('Expected a zero quantity to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
    }
}
