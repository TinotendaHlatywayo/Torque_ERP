<?php

namespace Tests\Feature\Performance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class PerformanceOptimizationTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branch = $this->branch($this->platform, 'PERF');
        $this->admin = $this->user('Perf Admin');
        $this->membership($this->branch, $this->admin, 'admin');
    }

    public function test_products_index_caps_per_page_and_uses_bounded_queries(): void
    {
        $unit = $this->unit($this->branch, 'PCS');
        for ($i = 1; $i <= 12; $i++) {
            $this->product($this->branch, $unit, null, 'P'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'Product '.$i);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?per_page=500");

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(12, 'data');

        $this->assertLessThanOrEqual(20, count(DB::getQueryLog()), 'Products index should not issue unbounded queries.');
    }

    public function test_customers_index_caps_per_page_and_returns_meta(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Customer::create([
                'branch_id' => $this->branch->id,
                'name' => 'Customer '.$i,
                'is_active' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/customers?per_page=999");

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(5, 'data');

        $this->assertLessThanOrEqual(20, count(DB::getQueryLog()));
    }

    public function test_sales_index_returns_summary_payload_without_n_plus_one_queries(): void
    {
        [$product, $unit] = $this->seedSaleProduct();
        $customer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Perf Customer',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 8; $i++) {
            $this->createPostedSale($product, $unit, $customer, $i, $i % 2 === 0 ? 2 : 1);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/sales?per_page=500");

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(8, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'sale_number',
                    'status',
                    'payment_status',
                    'currency_code',
                    'total_minor',
                    'paid_minor',
                    'customer',
                    'receipt',
                    'items_count',
                    'created_at',
                ]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertArrayNotHasKey('items', $response->json('data.0'));
        $this->assertArrayNotHasKey('payments', $response->json('data.0'));
        $this->assertLessThanOrEqual(20, count(DB::getQueryLog()), 'Sales index should avoid per-sale item/payment queries.');
    }

    public function test_stock_endpoints_cap_per_page_and_return_meta(): void
    {
        $unit = $this->unit($this->branch, 'PCS');
        $product = $this->product($this->branch, $unit, null, 'STK-1', 'Stock Product');
        $product->stockLevel()->update(['quantity_on_hand' => '5.0000']);

        for ($i = 1; $i <= 3; $i++) {
            $this->withAuthToken($this->admin)
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson("/api/branches/{$this->branch->id}/products/{$product->id}/stock/movements", [
                    'type' => $i === 1 ? 'opening' : 'correction',
                    'quantity' => $i === 1 ? '5' : '1',
                    'unit_id' => $unit->id,
                    'reason' => $i === 1 ? null : 'Adjustment '.$i,
                ])
                ->assertSuccessful();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $levels = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/stock/levels?per_page=500");

        $levels->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        $this->assertLessThanOrEqual(20, count(DB::getQueryLog()));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $movements = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/stock/movements?per_page=500");

        $movements->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        $this->assertLessThanOrEqual(20, count(DB::getQueryLog()));
    }

    public function test_pos_checkout_flow_completes_with_bounded_queries(): void
    {
        [$product, $unit] = $this->seedSaleProduct('10.0000');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = microtime(true);

        $response = $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branch->id}/checkout", [
                'lines' => [[
                    'product_id' => $product->id,
                    'unit_id' => $unit->id,
                    'quantity' => '2',
                ]],
                'payments' => [[
                    'method' => 'cash',
                    'amount_minor' => 2000,
                    'currency_code' => 'USD',
                ]],
            ]);

        $durationMs = (microtime(true) - $start) * 1000;

        $response->assertCreated()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.receipt.receipt_number', fn ($value) => is_string($value) && $value !== '');

        $queryCount = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(60, $queryCount, 'Checkout flow should remain bounded in query count.');
        $this->assertLessThan(5000, $durationMs, 'Checkout flow should complete within a reasonable test-harness budget.');

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'quantity_on_hand' => '8.0000',
        ]);
    }

    /**
     * @return array{0: Product, 1: Unit}
     */
    private function seedSaleProduct(string $stock = '20.0000'): array
    {
        $unit = $this->unit($this->branch, 'PCS');
        $product = $this->product($this->branch, $unit, null, 'POS-'.uniqid(), 'POS Product', null, 1000);
        $product->stockLevel()->update(['quantity_on_hand' => $stock]);

        return [$product, $unit];
    }

    private function createPostedSale(Product $product, Unit $unit, Customer $customer, int $index, int $itemCount): void
    {
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'sale_number' => 'SALE-PERF-'.$index,
            'status' => 'posted',
            'payment_status' => 'paid',
            'currency_code' => 'USD',
            'subtotal_minor' => 1000 * $itemCount,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 1000 * $itemCount,
            'paid_minor' => 1000 * $itemCount,
            'idempotency_key' => 'perf-sale-'.$index,
            'request_hash' => 'perf-hash-'.$index,
            'actor_id' => $this->admin->id,
        ]);

        for ($i = 1; $i <= $itemCount; $i++) {
            SaleItem::create([
                'branch_id' => $this->branch->id,
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_id' => $unit->id,
                'unit_code' => $unit->code,
                'quantity' => 1,
                'unit_price_minor' => 1000,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => 1000,
                'currency_code' => 'USD',
            ]);
        }

        Payment::create([
            'branch_id' => $this->branch->id,
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount_minor' => 1000 * $itemCount,
            'currency_code' => 'USD',
            'status' => 'recorded',
            'actor_id' => $this->admin->id,
        ]);

        Receipt::create([
            'branch_id' => $this->branch->id,
            'sale_id' => $sale->id,
            'receipt_number' => 'RCPT-PERF-'.$index,
            'issued_at' => now(),
        ]);
    }
}
