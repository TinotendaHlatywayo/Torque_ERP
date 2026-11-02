<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $cashier;

    private User $manager;

    private Product $product;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');

        $this->cashier = $this->user('Cashier');
        $this->manager = $this->user('Manager');

        $this->membership($this->branchA, $this->cashier, 'cashier');
        $this->membership($this->branchA, $this->manager, 'manager');

        $this->unit = $this->unit($this->branchA, 'PCS', true);
        $this->product = $this->product($this->branchA, $this->unit, null, 'REP-1', 'Report Product', null, 1000);
        $this->product->stockLevel()->update(['quantity_on_hand' => '10.0000']);
    }

    public function test_sales_report_returns_hand_calculated_totals(): void
    {
        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '2',
                        'discount_percent' => 5,
                        'tax_rate_percent' => 10,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 2090, 'currency_code' => 'USD'],
                ],
            ])
            ->assertCreated();

        $this->withAuthToken($this->manager)
            ->getJson("/api/branches/{$this->branchA->id}/reports/sales")
            ->assertOk()
            ->assertJsonPath('data.sale_count', 1)
            ->assertJsonPath('data.invoiced_revenue_minor', 2090)
            ->assertJsonPath('data.discount_minor', 100)
            ->assertJsonPath('data.tax_minor', 190)
            ->assertJsonPath('data.cash_collected_minor', 2090)
            ->assertJsonPath('data.outstanding_minor', 0);
    }

    public function test_stock_report_returns_balances(): void
    {
        $this->withAuthToken($this->manager)
            ->getJson("/api/branches/{$this->branchA->id}/reports/stock")
            ->assertOk()
            ->assertJsonPath('data.product_count', 1)
            ->assertJsonPath('data.quantity_on_hand', '10')
            ->assertJsonPath('data.inventory_value_at_selling_price_minor', 10000);
    }

    public function test_receivables_report_returns_outstanding(): void
    {
        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '1',
                    ],
                ],
                'payments' => [],
            ])
            ->assertCreated();

        $this->withAuthToken($this->manager)
            ->getJson("/api/branches/{$this->branchA->id}/reports/receivables")
            ->assertOk()
            ->assertJsonPath('data.outstanding_invoice_count', 1)
            ->assertJsonPath('data.outstanding_minor', 1000);
    }

    public function test_reports_require_permission(): void
    {
        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/reports/sales")
            ->assertForbidden();
    }
}
