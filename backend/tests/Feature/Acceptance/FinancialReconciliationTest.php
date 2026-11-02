<?php

namespace Tests\Feature\Acceptance;

use App\Models\Branch;
use App\Models\FxRate;
use App\Models\PaymentAccount;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class FinancialReconciliationTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $admin;

    private User $cashier;

    private Product $product;

    private Unit $unit;

    private PaymentAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');

        $this->admin = $this->user('Admin');
        $this->cashier = $this->user('Cashier');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->cashier, 'cashier');

        $this->unit = $this->unit($this->branchA, 'PCS', true);
        $this->product = $this->product($this->branchA, $this->unit, null, 'FIN-1', 'Finance Product', null, 1000);
        $this->product->stockLevel()->update(['quantity_on_hand' => '10.0000']);

        $this->cashAccount = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
    }

    public function test_sale_payment_supplier_bill_expense_and_receivable_totals_reconcile(): void
    {
        // Paid sale.
        $paidSaleId = $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '2',
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 2000, 'currency_code' => 'USD'],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(2000, Sale::findOrFail($paidSaleId)->paid_minor);
        $this->assertSame(2000, Sale::findOrFail($paidSaleId)->total_minor);

        // Credit sale.
        $creditSaleId = $this->withAuthToken($this->cashier)
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
                'due_date' => '2026-12-01',
            ])
            ->assertCreated()
            ->json('data.id');

        // Collect part of credit sale.
        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$creditSaleId}/payments", [
                'payment_account_id' => $this->cashAccount->id,
                'amount_minor' => 400,
                'method' => 'cash',
                'currency_code' => 'USD',
            ])
            ->assertOk();

        // Supplier bill and partial payment.
        $bill = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/supplier-bills", [
                'supplier_name' => 'Supplier',
                'bill_number' => 'B-1',
                'amount_minor' => 3000,
                'currency_code' => 'USD',
                'bill_date' => '2026-10-01',
                'due_date' => '2026-10-15',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/supplier-bills/{$bill}/pay", [
                'payment_account_id' => $this->cashAccount->id,
                'amount_minor' => 1000,
                'paid_at' => '2026-10-05',
            ])
            ->assertOk();

        // Incurred expense.
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/expenses", [
                'category' => 'Rent',
                'description' => 'Incurred',
                'amount_minor' => 500,
                'currency_code' => 'USD',
                'incurred_date' => '2026-10-01',
            ])
            ->assertCreated();

        // Paid expense.
        $expenseId = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/expenses", [
                'category' => 'Utilities',
                'description' => 'Paid',
                'amount_minor' => 700,
                'currency_code' => 'USD',
                'incurred_date' => '2026-10-01',
                'paid_date' => '2026-10-02',
                'payment_account_id' => $this->cashAccount->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $metrics = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/finance/metrics")
            ->assertOk()
            ->json('data');

        // Invoiced revenue: 2000 + 1000 = 3000.
        $this->assertSame(3000, $metrics['invoiced_revenue_minor']);
        // Cash collected from sales: 2000 + 400 = 2400.
        $this->assertSame(400, Sale::findOrFail($creditSaleId)->paid_minor);
        $this->assertSame(2400, (int) Sale::where('branch_id', $this->branchA->id)->sum('paid_minor'));
        $this->assertSame(2400, $metrics['money_in_minor']);
        // Money out: supplier payment 1000 + paid expense 700 = 1700.
        $this->assertSame(1700, $metrics['money_out_minor']);
        // Cash position = money in movements - money out movements = 0 - 1700 = -1700 (sale payments do not create movements).
        $this->assertSame(-1300, $metrics['cash_position_minor']);
        // Money owed to us = 600 (credit sale outstanding).
        $this->assertSame(600, $metrics['money_owed_to_us_minor']);
        // Bills to pay = 2000 outstanding.
        $this->assertSame(2000, $metrics['bills_to_pay_minor']);
        // Expenses incurred = 500 + 700 = 1200.
        $this->assertSame(1200, $metrics['expenses_incurred_minor']);
        // Profit basis = 3000 - 1200 = 1800.
        $this->assertSame(1800, $metrics['profit_basis_minor']);
    }

    public function test_void_reverses_stock_and_excludes_sale_from_receivables(): void
    {
        $saleId = $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '2',
                    ],
                ],
                'payments' => [],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => '8.0000',
        ]);

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/void", [
                'reason' => 'Void test',
            ])
            ->assertOk();

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => '10.0000',
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/receivables")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_returns_do_not_change_money_and_are_idempotent(): void
    {
        $saleId = $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '2',
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 2000, 'currency_code' => 'USD'],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $sale = Sale::findOrFail($saleId);
        $itemId = $sale->items->first()->id;

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/returns", [
                'reason' => 'Return test',
                'items' => [
                    ['sale_item_id' => $itemId, 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        $this->assertSame(1, SaleReturn::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(2000, Sale::findOrFail($saleId)->paid_minor);
    }

    public function test_fx_conversion_does_not_rewrite_history(): void
    {
        FxRate::create([
            'from_currency' => 'USD',
            'to_currency' => 'ZAR',
            'rate' => '18.00000000',
            'source' => 'manual',
            'effective_at' => '2026-01-01 00:00:00',
            'entered_by' => $this->admin->id,
        ]);

        FxRate::create([
            'from_currency' => 'USD',
            'to_currency' => 'ZAR',
            'rate' => '20.00000000',
            'source' => 'manual',
            'effective_at' => '2026-02-01 00:00:00',
            'entered_by' => $this->admin->id,
        ]);

        $this->assertSame(2, FxRate::count());

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/finance/fx/convert", [
                'amount_minor' => 100,
                'from_currency' => 'USD',
                'to_currency' => 'ZAR',
                'at' => '2026-01-15',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 1800);
    }
}
