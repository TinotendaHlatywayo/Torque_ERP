<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\MoneyMovement;
use App\Models\PaymentAccount;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SupplierBill;
use App\Models\Unit;
use App\Models\User;
use App\Support\StockLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class DashboardV2Test extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private const AS_OF = '2026-06-30';

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private Unit $unit;

    private User $admin;

    private User $storekeeper;

    private User $accountant;

    private User $restricted;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'DASH-A');
        $this->branchB = $this->branch($this->platform, 'DASH-B');
        $this->unit = $this->unit($this->branchA, 'DASH-PCS');

        $this->admin = $this->user('Dashboard Admin A');
        $this->storekeeper = $this->user('Dashboard Storekeeper A');
        $this->accountant = $this->user('Dashboard Accountant A');
        $this->restricted = $this->user('Dashboard Restricted A');
        $this->adminB = $this->user('Dashboard Admin B');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->storekeeper, 'storekeeper');
        $this->membership($this->branchA, $this->accountant, 'accountant');
        $this->membership($this->branchA, $this->restricted, 'restricted', true, []);
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_reorder_suggestions_use_configurable_windows_on_known_fixtures(): void
    {
        config()->set('inventory_intelligence.reorder', [
            'sales_window_days' => 10,
            'lead_time_days' => 2,
            'days_of_cover' => 3,
        ]);

        $fast = $this->productWithStock('DASH-REORDER-A', '5');
        $noSales = $this->productWithStock('DASH-REORDER-B', '30');
        $oldSales = $this->productWithStock('DASH-REORDER-C', '5');

        $this->sale($fast, '40', 4000, CarbonImmutable::parse(self::AS_OF, 'UTC')->subDays(2));
        $this->sale($oldSales, '100', 10000, CarbonImmutable::parse(self::AS_OF, 'UTC')->subDays(40));

        $data = $this->dashboard($this->admin)['inventory_intelligence']['reorder'];

        $this->assertSame(10, $data['sales_window_days']);
        $this->assertSame(2, $data['lead_time_days']);
        $this->assertSame(3, $data['days_of_cover']);
        $this->assertSame(1, $data['needs_reorder_count']);
        $this->assertSame(2, $data['products_without_history']);
        $this->assertCount(1, $data['suggestions']);

        $suggestion = $data['suggestions'][0];
        $this->assertSame($fast->id, $suggestion['product_id']);
        $this->assertEquals(4.0, $suggestion['average_daily_sales_units']);
        $this->assertEquals(20.0, $suggestion['reorder_point_units']);
        $this->assertSame('5.0000', $suggestion['quantity_available']);
        $this->assertSame(15, $suggestion['suggested_order_units']);
        $this->assertSame('reorder_now', $suggestion['urgency']);
        $this->assertSame('insufficient_sales_history', $data['data_quality']['warnings'][0]['code']);
        $this->assertFalse(in_array($noSales->id, array_column($data['suggestions'], 'product_id'), true));
        $this->assertFalse(in_array($oldSales->id, array_column($data['suggestions'], 'product_id'), true));

        config()->set('inventory_intelligence.reorder.sales_window_days', 20);

        $widened = $this->dashboard($this->admin)['inventory_intelligence']['reorder']['suggestions'][0];
        $this->assertEquals(2.0, $widened['average_daily_sales_units']);
        $this->assertEquals(10.0, $widened['reorder_point_units']);
        $this->assertSame(5, $widened['suggested_order_units']);
    }

    public function test_ageing_and_dead_stock_classification_use_last_movement_and_thresholds(): void
    {
        config()->set('inventory_intelligence.ageing', [
            'slow_moving_threshold_days' => 30,
            'dead_stock_threshold_days' => 90,
        ]);

        $active = $this->stockedProduct('DASH-AGE-ACTIVE', 5);
        $slow = $this->stockedProduct('DASH-AGE-SLOW', 45);
        $dead = $this->stockedProduct('DASH-AGE-DEAD', 100);
        $noHistory = $this->productWithStock('DASH-AGE-NONE', '5');
        $empty = $this->productWithStock('DASH-AGE-EMPTY', '0');

        $this->assertNotNull($active);
        $this->assertNotNull($slow);
        $this->assertNotNull($dead);

        $data = $this->dashboard($this->admin)['inventory_intelligence']['ageing'];
        $bySku = collect($data['items'])->keyBy('sku');

        $this->assertSame(45, $bySku->get('DASH-AGE-SLOW')['age_days']);
        $this->assertSame('active', $bySku->get('DASH-AGE-ACTIVE')['classification']);
        $this->assertSame('slow_moving', $bySku->get('DASH-AGE-SLOW')['classification']);
        $this->assertSame('dead_stock', $bySku->get('DASH-AGE-DEAD')['classification']);
        $this->assertSame(100, $bySku->get('DASH-AGE-DEAD')['age_days']);
        $this->assertNull($bySku->get('DASH-AGE-NONE')['classification']);
        $this->assertSame('unknown', $bySku->get('DASH-AGE-NONE')['status']);
        $this->assertSame('no_stock', $bySku->get('DASH-AGE-EMPTY')['classification']);
        $this->assertSame(1, $data['buckets']['active']['product_count']);
        $this->assertSame(1, $data['buckets']['slow_moving']['product_count']);
        $this->assertSame(1, $data['buckets']['dead_stock']['product_count']);
        $this->assertSame(1, $data['buckets']['unknown']['product_count']);
        $this->assertSame(1, $data['buckets']['no_stock']['product_count']);
        $this->assertSame('no_movement_history', $data['data_quality']['warnings'][0]['code']);
    }

    public function test_abc_classification_uses_configurable_contribution_and_window(): void
    {
        config()->set('inventory_intelligence.abc', [
            'sales_window_days' => 10,
            'a_cumulative_contribution_percent' => 70,
            'b_cumulative_contribution_percent' => 90,
        ]);

        $first = $this->productWithStock('DASH-ABC-A', '10');
        $second = $this->productWithStock('DASH-ABC-B', '10');
        $third = $this->productWithStock('DASH-ABC-C', '10');
        $outside = $this->productWithStock('DASH-ABC-OUT', '10');

        $asOf = CarbonImmutable::parse(self::AS_OF, 'UTC');
        $this->sale($first, '7', 7000, $asOf->subDays(1));
        $this->sale($second, '2', 2000, $asOf->subDays(2));
        $this->sale($third, '1', 1000, $asOf->subDays(3));
        $this->sale($outside, '10', 10000, $asOf->subDays(40));

        $data = $this->dashboard($this->admin)['inventory_intelligence']['abc'];
        $bySku = collect($data['items'])->keyBy('sku');

        $this->assertSame(10000, $data['total_revenue_minor']);
        $this->assertSame('A', $bySku->get('DASH-ABC-A')['class']);
        $this->assertEquals(70.0, $bySku->get('DASH-ABC-A')['cumulative_percent']);
        $this->assertSame('B', $bySku->get('DASH-ABC-B')['class']);
        $this->assertEquals(90.0, $bySku->get('DASH-ABC-B')['cumulative_percent']);
        $this->assertSame('C', $bySku->get('DASH-ABC-C')['class']);
        $this->assertNull($bySku->get('DASH-ABC-OUT'));
        $this->assertSame(1, $data['summary']['A']['product_count']);
        $this->assertSame(7000, $data['summary']['A']['revenue_minor']);
        $this->assertSame('PASS', $data['data_quality']['status']);

        config()->set('inventory_intelligence.abc.a_cumulative_contribution_percent', 60);

        $changed = collect($this->dashboard($this->admin)['inventory_intelligence']['abc']['items'])->keyBy('sku');
        $this->assertSame('B', $changed->get('DASH-ABC-A')['class']);
        $this->assertSame('B', $changed->get('DASH-ABC-B')['class']);
        $this->assertSame('C', $changed->get('DASH-ABC-C')['class']);
    }

    public function test_cash_forecast_is_labelled_forecast_with_assumptions_and_known_commitments(): void
    {
        config()->set('inventory_intelligence.cash_forecast', [
            'history_window_days' => 10,
            'horizon_days' => 5,
            'minimum_cash_events' => 2,
        ]);

        $account = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
        $product = $this->productWithStock('DASH-CASH-PRODUCT', '10');
        $this->sale($product, '1', 5000, CarbonImmutable::parse(self::AS_OF, 'UTC')->subDays(2), 5000);
        $this->moneyMovement($account, 'in', 5000, 'manual_sale', 2);
        $this->moneyMovement($account, 'out', 1000, 'expense_payment', 3);

        SupplierBill::create([
            'branch_id' => $this->branchA->id,
            'supplier_name' => 'Supplier One',
            'bill_number' => 'DASH-BILL-1',
            'amount_minor' => 2000,
            'currency_code' => 'USD',
            'bill_date' => CarbonImmutable::parse(self::AS_OF, 'UTC')->subDays(4)->toDateString(),
            'due_date' => CarbonImmutable::parse(self::AS_OF, 'UTC')->addDays(2)->toDateString(),
            'paid_minor' => 0,
            'status' => 'open',
        ]);

        Expense::create([
            'branch_id' => $this->branchA->id,
            'category' => 'utilities',
            'description' => 'Unpaid power bill',
            'amount_minor' => 500,
            'currency_code' => 'USD',
            'incurred_date' => CarbonImmutable::parse(self::AS_OF, 'UTC')->subDays(1)->toDateString(),
            'paid_date' => null,
            'status' => 'incurred',
        ]);

        $data = $this->dashboard($this->admin)['cash_forecast'];

        $this->assertSame('FORECAST', $data['label']);
        $this->assertFalse($data['authoritative_finance_statement']);
        $this->assertNotEmpty($data['assumptions']);
        $this->assertStringContainsString('straight-line', implode(' ', $data['assumptions']));
        $this->assertSame(4000, $data['beginning_cash_minor']);
        $this->assertSame(1000, $data['average_daily_inflow_minor']);
        $this->assertSame(100, $data['average_daily_outflow_minor']);
        $this->assertSame(5000, $data['projected_inflow_minor']);
        $this->assertSame(500, $data['projected_outflow_minor']);
        $this->assertSame(2000, $data['known_bills_due_minor']);
        $this->assertSame(2500, $data['cash_target_minor']);
        $this->assertSame(6500, $data['forecast_cash_position_minor']);
        $this->assertCount(5, $data['series']);
        $this->assertSame('PASS', $data['data_quality']['status']);
    }

    public function test_insufficient_data_returns_warnings_and_null_or_zero_values(): void
    {
        $this->productWithStock('DASH-EMPTY-REORDER', '5');

        $data = $this->dashboard($this->admin);

        $this->assertSame('WARNING', $data['data_quality']['status']);
        $this->assertSame('insufficient_sales_history', $data['inventory_intelligence']['reorder']['data_quality']['warnings'][0]['code']);
        $this->assertSame([], $data['inventory_intelligence']['reorder']['suggestions']);
        $this->assertSame('insufficient_sales_data', $data['inventory_intelligence']['abc']['data_quality']['warnings'][0]['code']);
        $this->assertSame(0, $data['inventory_intelligence']['abc']['total_revenue_minor']);
        $this->assertSame([], $data['inventory_intelligence']['abc']['items']);
        $this->assertSame('insufficient_cash_history', $data['cash_forecast']['data_quality']['warnings'][0]['code']);
        $this->assertNull($data['cash_forecast']['average_daily_inflow_minor']);
        $this->assertNull($data['cash_forecast']['forecast_cash_position_minor']);
        $this->assertSame([], $data['cash_forecast']['series']);
        $this->assertSame(0, $data['cash_forecast']['cash_target_minor']);
    }

    public function test_dashboard_is_permission_aware_and_branch_scoped(): void
    {
        $product = $this->productWithStock('DASH-SCOPE-A', '10');
        $this->sale($product, '3', 3000, CarbonImmutable::parse(self::AS_OF, 'UTC')->subDays(1));

        $unitB = $this->unit($this->branchB, 'DASH-B-PCS');
        $foreignProduct = $this->product($this->branchB, $unitB, null, 'DASH-SCOPE-B', 'Branch B product', null, 1000);
        $this->saleFor($this->branchB, $foreignProduct, '9', 9000, CarbonImmutable::parse(self::AS_OF, 'UTC')->subDays(1));

        $adminData = $this->dashboard($this->admin);
        $this->assertNotNull($adminData['inventory_intelligence']);
        $this->assertNotNull($adminData['cash_forecast']);
        $this->assertSame(
            [$product->id],
            array_column($adminData['inventory_intelligence']['reorder']['suggestions'], 'product_id'),
        );

        $storekeeperData = $this->dashboard($this->storekeeper);
        $this->assertNotNull($storekeeperData['inventory_intelligence']);
        $this->assertNull($storekeeperData['cash_forecast']);
        $this->assertNull($storekeeperData['finance_metrics']);
        $this->assertFalse($storekeeperData['capabilities']['finance']);

        $accountantData = $this->dashboard($this->accountant);
        $this->assertNull($accountantData['inventory_intelligence']);
        $this->assertNotNull($accountantData['cash_forecast']);
        $this->assertFalse($accountantData['capabilities']['inventory']);

        $this->withAuthToken($this->restricted)
            ->getJson($this->endpoint())
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/dashboard/v2?as_of=".self::AS_OF)
            ->assertForbidden();

        $this->withAuthToken($this->adminB)
            ->getJson($this->endpoint())
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(User $user): array
    {
        return $this->withAuthToken($user)
            ->getJson($this->endpoint())
            ->assertOk()
            ->json('data');
    }

    private function endpoint(): string
    {
        return "/api/branches/{$this->branchA->id}/dashboard/v2?as_of=".self::AS_OF;
    }

    private function productWithStock(string $sku, string $quantity): Product
    {
        $product = $this->product($this->branchA, $this->unit, null, $sku, 'Product '.$sku, null, 1000);
        $product->stockLevel()->update(['quantity_on_hand' => $quantity]);

        return $product->refresh();
    }

    private function stockedProduct(string $sku, int $daysAgo): Product
    {
        $product = $this->productWithStock($sku, '10');
        $movement = app(StockLedger::class)->postOpening(
            $this->branchA,
            $product,
            '10',
            $this->unit->id,
            'Ageing fixture',
            $this->admin,
            'dashboard-ageing-'.$sku,
        );

        $movement->movement->forceFill([
            'created_at' => CarbonImmutable::parse(self::AS_OF, 'UTC')->subDays($daysAgo)->toDateTimeString(),
        ])->save();

        return $product->refresh();
    }

    private function sale(Product $product, string $quantity, int $revenueMinor, CarbonImmutable $createdAt, ?int $paidMinor = null): Sale
    {
        return $this->saleFor($this->branchA, $product, $quantity, $revenueMinor, $createdAt, $paidMinor);
    }

    private function saleFor(Branch $branch, Product $product, string $quantity, int $revenueMinor, CarbonImmutable $createdAt, ?int $paidMinor = null): Sale
    {
        $reference = uniqid('dash-sale-');
        $sale = Sale::create([
            'branch_id' => $branch->id,
            'sale_number' => $reference,
            'status' => 'posted',
            'payment_status' => 'paid',
            'currency_code' => $branch->currency_code,
            'subtotal_minor' => $revenueMinor,
            'total_minor' => $revenueMinor,
            'paid_minor' => $paidMinor ?? $revenueMinor,
            'idempotency_key' => $reference,
            'request_hash' => hash('sha256', $reference),
            'actor_id' => $this->admin->id,
        ]);

        SaleItem::create([
            'branch_id' => $branch->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_id' => $product->base_unit_id,
            'unit_code' => $this->unit->code,
            'quantity' => $quantity,
            'unit_price_minor' => $revenueMinor,
            'total_minor' => $revenueMinor,
            'currency_code' => $branch->currency_code,
        ]);

        $sale->forceFill([
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $createdAt->toDateTimeString(),
        ])->save();

        return $sale->refresh();
    }

    private function moneyMovement(PaymentAccount $account, string $direction, int $amountMinor, string $source, int $daysAgo): MoneyMovement
    {
        $reference = uniqid('dash-movement-');
        $movement = MoneyMovement::create([
            'branch_id' => $this->branchA->id,
            'payment_account_id' => $account->id,
            'movement_type' => $direction === 'in' ? 'manual_in' : 'manual_out',
            'amount_minor' => $amountMinor,
            'currency_code' => 'USD',
            'direction' => $direction,
            'source' => $source,
            'reason' => 'Dashboard forecast fixture',
            'idempotency_key' => $reference,
            'request_hash' => hash('sha256', $reference),
            'actor_id' => $this->admin->id,
        ]);

        $movement->forceFill([
            'created_at' => CarbonImmutable::parse(self::AS_OF, 'UTC')->subDays($daysAgo)->toDateTimeString(),
        ])->save();

        return $movement->refresh();
    }
}
