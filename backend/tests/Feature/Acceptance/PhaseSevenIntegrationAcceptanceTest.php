<?php

namespace Tests\Feature\Acceptance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\LoyaltyQualification;
use App\Models\LoyaltySaleEvent;
use App\Models\MoneyMovement;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\ServiceJob;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkshopService;
use App\Support\StockLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class PhaseSevenIntegrationAcceptanceTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private const AS_OF = '2026-06-30';

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $adminA;

    private User $adminB;

    private User $bothAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'ACC-A');
        $this->branchB = $this->branch($this->platform, 'ACC-B');
        $this->branchA->update(['status' => Branch::STATUS_ACTIVE]);
        $this->branchB->update(['status' => Branch::STATUS_ACTIVE]);

        $this->adminA = $this->user('Phase Seven Admin A');
        $this->adminB = $this->user('Phase Seven Admin B');
        $this->bothAdmin = $this->user('Phase Seven Both Admin');

        $this->membership($this->branchA, $this->adminA, 'admin');
        $this->membership($this->branchB, $this->adminB, 'admin');
        $this->membership($this->branchA, $this->bothAdmin, 'admin');
        $this->membership($this->branchB, $this->bothAdmin, 'admin');
    }

    public function test_job_labour_parts_invoice_and_retries_post_once_without_duplicate_stock_or_expense(): void
    {
        $unit = $this->unit($this->branchA, 'ACC-JOB-PCS');
        $part = $this->stockedProduct($this->branchA, $unit, 'ACC-JOB-PART', $this->adminA, '10');
        $service = $this->workshopService($this->branchA, 'ACC-JOB-SERVICE', '2.00', 1250);
        $customer = $this->customer($this->branchA, 'Job Acceptance Customer');
        $job = $this->createJob($customer, $this->adminA, $this->adminA->id);

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/labour", [
                'workshop_service_id' => $service->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount_minor', 2500);

        $partPayload = [
            'product_id' => $part->id,
            'unit_id' => $unit->id,
            'quantity' => '2',
            'unit_price_minor' => 1000,
        ];

        $firstPart = $this->withAuthToken($this->adminA)
            ->postJson(
                "/api/branches/{$this->branchA->id}/jobs/{$job->id}/parts",
                $partPayload,
                ['Idempotency-Key' => 'acc-job-part-1'],
            )
            ->assertCreated();

        $replayedPart = $this->withAuthToken($this->adminA)
            ->postJson(
                "/api/branches/{$this->branchA->id}/jobs/{$job->id}/parts",
                $partPayload,
                ['Idempotency-Key' => 'acc-job-part-1'],
            )
            ->assertCreated();

        $partLineId = $firstPart->json('data.id');

        $this->assertFalse($firstPart->json('idempotent_replay'));
        $this->assertTrue($replayedPart->json('idempotent_replay'));
        $this->assertSame($partLineId, $replayedPart->json('data.id'));

        $this->withAuthToken($this->adminA)
            ->postJson(
                "/api/branches/{$this->branchA->id}/jobs/{$job->id}/parts",
                [...$partPayload, 'quantity' => '3'],
                ['Idempotency-Key' => 'acc-job-part-1'],
            )
            ->assertStatus(409);

        $estimate = $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/estimate")
            ->assertOk()
            ->json('data');

        $this->assertSame(4500, $estimate['estimate_minor']);
        $this->assertSame(1, $estimate['line_counts']['labour']);
        $this->assertSame(1, $estimate['line_counts']['parts']);

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/approve", [
                'approval_reference' => 'ACC-JOB-APPROVAL',
            ])
            ->assertOk();
        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/start")
            ->assertOk();
        $this->withAuthToken($this->adminA)
            ->patchJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}", ['diagnosis' => 'Brake pads replaced'])
            ->assertOk();
        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/complete")
            ->assertOk();

        $this->assertSame(0, Sale::count());

        $invoice = $this->manualInvoice($this->branchA, $customer, 4500, 'acc-job-invoice');

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/invoice", ['sale_id' => $invoice->id])
            ->assertOk()
            ->assertJsonPath('data.sale_id', $invoice->id);

        $movements = StockMovement::where('branch_id', $this->branchA->id)
            ->where('product_id', $part->id)
            ->get();

        $this->assertCount(2, $movements);
        $this->assertSame(1, $movements->where('source', StockMovement::SOURCE_OPENING)->count());
        $jobMovements = $movements->where('source', StockMovement::SOURCE_JOB)->values();
        $this->assertCount(1, $jobMovements);
        $this->assertSame(StockMovement::TYPE_JOB_CONSUMPTION, $jobMovements[0]->movement_type);
        $this->assertSame((string) $partLineId, (string) $jobMovements[0]->source_id);
        $this->assertSame('-2.0000', (string) $jobMovements[0]->quantity);
        $this->assertSame('8.0000', (string) $part->stockLevel()->first()->quantity_on_hand);

        $this->assertSame(1, $job->labourLines()->count());
        $this->assertSame(1, $job->partLines()->count());
        $this->assertSame(1, Sale::count());
        $this->assertSame($invoice->id, $job->refresh()->sale_id);
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, MoneyMovement::count());
    }

    public function test_transfer_receipt_and_stocktake_approval_move_stock_once_and_reject_duplicate_retries(): void
    {
        $sourceUnit = $this->unit($this->branchA, 'ACC-SRC-PCS');
        $destinationUnit = $this->unit($this->branchB, 'ACC-DST-PCS');
        $sourceProduct = $this->stockedProduct($this->branchA, $sourceUnit, 'ACC-TRANSFER-SRC', $this->bothAdmin, '10');
        $destinationProduct = $this->stockedProduct($this->branchB, $destinationUnit, 'ACC-TRANSFER-DST', $this->bothAdmin, '0');

        $transfer = $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->branchA->id}/stock-transfers", [
                'destination_branch_id' => $this->branchB->id,
                'product_id' => $sourceProduct->id,
                'unit_id' => $sourceUnit->id,
                'destination_product_id' => $destinationProduct->id,
                'destination_unit_id' => $destinationUnit->id,
                'quantity' => '3',
                'reason' => 'Branch replenishment',
            ])
            ->assertCreated()
            ->json('data');

        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->branchA->id}/stock-transfers/{$transfer['id']}/dispatch")
            ->assertOk()
            ->assertJsonPath('data.status', 'dispatched');
        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->branchA->id}/stock-transfers/{$transfer['id']}/in-transit")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit');
        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->branchA->id}/stock-transfers/{$transfer['id']}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', 'received');
        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->branchA->id}/stock-transfers/{$transfer['id']}/receive")
            ->assertStatus(409);

        $this->assertSame('7.0000', (string) $sourceProduct->stockLevel()->first()->quantity_on_hand);
        $this->assertSame('3.0000', (string) $destinationProduct->stockLevel()->first()->quantity_on_hand);

        $sourceTransferMovements = StockMovement::where('branch_id', $this->branchA->id)
            ->where('source', StockMovement::SOURCE_STOCK_TRANSFER)
            ->where('source_id', $transfer['id'])
            ->get();
        $destinationTransferMovements = StockMovement::where('branch_id', $this->branchB->id)
            ->where('source', StockMovement::SOURCE_STOCK_TRANSFER)
            ->where('source_id', $transfer['id'])
            ->get();

        $this->assertCount(1, $sourceTransferMovements);
        $this->assertSame(StockMovement::TYPE_TRANSFER_OUT, $sourceTransferMovements[0]->movement_type);
        $this->assertSame('-3.0000', (string) $sourceTransferMovements[0]->quantity);
        $this->assertCount(1, $destinationTransferMovements);
        $this->assertSame(StockMovement::TYPE_TRANSFER_IN, $destinationTransferMovements[0]->movement_type);
        $this->assertSame('3.0000', (string) $destinationTransferMovements[0]->quantity);

        $stocktake = $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes", [
                'reason' => 'Post-transfer count',
                'lines' => [[
                    'product_id' => $sourceProduct->id,
                    'unit_id' => $sourceUnit->id,
                    'counted_quantity' => '6',
                    'reason' => 'Damaged during transfer',
                ]],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('-1.0000', $stocktake['lines'][0]['variance']);

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktake['id']}/review")
            ->assertOk();
        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktake['id']}/approve", [
                'approval_reference' => 'ACC-STOCKTAKE-APPROVAL',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktake['id']}/approve", [
                'approval_reference' => 'ACC-STOCKTAKE-DUPLICATE',
            ])
            ->assertStatus(409);

        $this->assertSame('6.0000', (string) $sourceProduct->stockLevel()->first()->quantity_on_hand);
        $this->assertSame('3.0000', (string) $destinationProduct->stockLevel()->first()->quantity_on_hand);

        $corrections = StockMovement::where('branch_id', $this->branchA->id)
            ->where('source', StockMovement::SOURCE_STOCKTAKE)
            ->get();

        $this->assertCount(1, $corrections);
        $this->assertSame(StockMovement::TYPE_CORRECTION, $corrections[0]->movement_type);
        $this->assertSame('-1.0000', (string) $corrections[0]->quantity);
        $this->assertCount(3, StockMovement::where('branch_id', $this->branchA->id)->where('product_id', $sourceProduct->id)->get());
        $this->assertCount(1, StockMovement::where('branch_id', $this->branchB->id)->where('product_id', $destinationProduct->id)->get());
        $this->assertSame(1, Stocktake::whereKey($stocktake['id'])->where('status', Stocktake::STATUS_APPROVED)->count());
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, MoneyMovement::count());
    }

    public function test_loyalty_discount_return_reports_and_dashboard_do_not_double_count_amounts(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::AS_OF.' 12:00:00', 'UTC'));
        config()->set('inventory_intelligence.reorder', [
            'sales_window_days' => 10,
            'lead_time_days' => 2,
            'days_of_cover' => 3,
        ]);
        config()->set('inventory_intelligence.abc', [
            'sales_window_days' => 10,
            'a_cumulative_contribution_percent' => 70,
            'b_cumulative_contribution_percent' => 90,
        ]);
        config()->set('inventory_intelligence.cash_forecast', [
            'history_window_days' => 10,
            'horizon_days' => 5,
            'minimum_cash_events' => 1,
        ]);

        $unit = $this->unit($this->branchA, 'ACC-SALE-PCS');
        $product = $this->stockedProduct($this->branchA, $unit, 'ACC-SALE-PRODUCT', $this->adminA, '10');
        $customer = $this->customer($this->branchA, 'Loyalty Acceptance Customer');

        $checkoutPayload = [
            'customer_id' => $customer->id,
            'lines' => [[
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => '2',
                'discount_percent' => 5,
                'tax_rate_percent' => 0,
            ]],
            'payments' => [[
                'method' => 'cash',
                'amount_minor' => 1900,
                'currency_code' => 'USD',
            ]],
        ];

        $checkout = $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/checkout", $checkoutPayload, ['Idempotency-Key' => 'acc-checkout-1'])
            ->assertCreated()
            ->assertJsonPath('data.subtotal_minor', 2000)
            ->assertJsonPath('data.discount_minor', 100)
            ->assertJsonPath('data.total_minor', 1900)
            ->assertJsonPath('data.paid_minor', 1900);

        $replayedCheckout = $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/checkout", $checkoutPayload, ['Idempotency-Key' => 'acc-checkout-1'])
            ->assertCreated();

        $saleId = $checkout->json('data.id');

        $this->assertSame($saleId, $replayedCheckout->json('data.id'));
        $this->assertSame(1, Sale::count());
        $this->assertSame('8.0000', (string) $product->stockLevel()->first()->quantity_on_hand);

        $sale = Sale::findOrFail($saleId);

        $this->configureLoyalty($this->branchA, [
            'min_qualifying_minor' => 1000,
            'loyalty_discount_percent' => 5,
            'combined_approval_threshold_percent' => 10,
        ]);

        $firstRecalculation = $this->recalculateLoyalty($this->branchA);
        $this->assertSame(1, $firstRecalculation['customers_processed']);
        $this->assertSame(1, LoyaltySaleEvent::where('branch_id', $this->branchA->id)->count());

        $saleEventPayload = [
            'customer_id' => $customer->id,
            'event_key' => 'sale:'.$sale->id,
            'source_ref' => $sale->sale_number,
            'total_minor' => 1900,
            'occurred_at' => $sale->created_at?->toIso8601String(),
        ];

        $event = $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/loyalty/sale-events", $saleEventPayload)
            ->assertCreated()
            ->json('data');

        $replayedEvent = $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/loyalty/sale-events", $saleEventPayload)
            ->assertCreated()
            ->json('data');

        $this->assertSame($event['id'], $replayedEvent['id']);

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/loyalty/sale-events", [
                ...$saleEventPayload,
                'total_minor' => 9999,
            ])
            ->assertStatus(409);

        $this->recalculateLoyalty($this->branchA);
        $this->assertSame(1, LoyaltySaleEvent::where('branch_id', $this->branchA->id)->count());

        $qualification = $this->qualificationFor($customer);
        $this->assertSame(1, $qualification->qualifying_sale_count);
        $this->assertSame(1900, $qualification->gross_sales_minor);
        $this->assertSame(0, $qualification->returned_minor);
        $this->assertSame(1900, $qualification->net_qualifying_minor);
        $this->assertTrue($qualification->qualifies);

        $quote = $this->discountQuote($customer, ['manual_discount_percent' => 2])
            ->assertOk()
            ->json('data');

        $this->assertEquals(7.0, $quote['combined_discount_percent']);
        $this->assertSame(2000, $quote['subtotal_minor']);
        $this->assertSame(1860, $quote['net_minor']);
        $this->assertFalse($quote['requires_approval']);

        $this->discountQuote($customer, ['manual_discount_percent' => 8])
            ->assertStatus(422)
            ->assertJsonValidationErrors('approval_reason');

        $approvedQuote = $this->discountQuote($customer, [
            'manual_discount_percent' => 8,
            'approval_reason' => 'Manager approved combined promotion',
        ])
            ->assertOk()
            ->json('data');

        $this->assertEquals(13.0, $approvedQuote['combined_discount_percent']);
        $this->assertTrue($approvedQuote['requires_approval']);
        $this->assertTrue($approvedQuote['approved']);

        $floorQuote = $this->discountQuote($customer, [
            'manual_discount_percent' => 8,
            'floor_price_minor' => 1900,
            'approval_reason' => 'Manager approved floor price',
        ])
            ->assertOk()
            ->json('data');

        $this->assertSame(1900, $floorQuote['net_minor']);
        $this->assertTrue($floorQuote['floor_price_applied']);

        $this->assertSame(1, Sale::count());
        $this->assertSame(100, Sale::findOrFail($saleId)->discount_minor);
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, MoneyMovement::count());

        $saleItem = $sale->items()->firstOrFail();
        $returnPayload = [
            'reason' => 'Customer changed mind',
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => '1']],
        ];

        $return = $this->withAuthToken($this->adminA)
            ->postJson(
                "/api/branches/{$this->branchA->id}/sales/{$saleId}/returns",
                $returnPayload,
                ['Idempotency-Key' => 'acc-return-1'],
            )
            ->assertCreated()
            ->assertJsonPath('data.total_minor', 950);

        $replayedReturn = $this->withAuthToken($this->adminA)
            ->postJson(
                "/api/branches/{$this->branchA->id}/sales/{$saleId}/returns",
                $returnPayload,
                ['Idempotency-Key' => 'acc-return-1'],
            )
            ->assertCreated();

        $this->assertSame($return->json('data.id'), $replayedReturn->json('data.id'));
        $this->assertSame(1, SaleReturn::where('branch_id', $this->branchA->id)->count());

        $this->recalculateLoyalty($this->branchA);
        $qualification->refresh();

        $this->assertSame(950, $qualification->returned_minor);
        $this->assertSame(950, $qualification->net_qualifying_minor);
        $this->assertFalse($qualification->qualifies);

        $this->discountQuote($customer, ['manual_discount_percent' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');

        $salesReport = $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchA->id}/reports/sales?from=2026-06-01&to=2026-06-30")
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $salesReport['sale_count']);
        $this->assertSame(1900, $salesReport['invoiced_revenue_minor']);
        $this->assertSame(100, $salesReport['discount_minor']);
        $this->assertSame(1900, $salesReport['cash_collected_minor']);
        $this->assertSame(0, $salesReport['outstanding_minor']);

        $stockReport = $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchA->id}/reports/stock")
            ->assertOk()
            ->json('data');

        $this->assertSame('8', $stockReport['quantity_on_hand']);
        $this->assertSame(8000, $stockReport['inventory_value_at_selling_price_minor']);

        $dashboard = $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchA->id}/dashboard/v2?as_of=".self::AS_OF)
            ->assertOk()
            ->json('data');

        $this->assertSame(1900, $dashboard['finance_metrics']['invoiced_revenue_minor']);
        $this->assertSame(1900, $dashboard['finance_metrics']['money_in_minor']);
        $this->assertSame(0, $dashboard['finance_metrics']['expenses_incurred_minor']);
        $this->assertSame(1900, $dashboard['finance_metrics']['profit_basis_minor']);
        $this->assertSame(0, $dashboard['finance_metrics']['cash_position_minor']);
        $this->assertSame(1900, $dashboard['inventory_intelligence']['abc']['total_revenue_minor']);
        $this->assertSame(1, $dashboard['inventory_intelligence']['abc']['summary']['C']['product_count']);
        $this->assertSame(0, $dashboard['inventory_intelligence']['reorder']['needs_reorder_count']);
        $this->assertCount(1, $dashboard['inventory_intelligence']['reorder']['suggestions']);
        $this->assertFalse($dashboard['inventory_intelligence']['reorder']['suggestions'][0]['needs_reorder']);
        $this->assertSame(0, $dashboard['inventory_intelligence']['reorder']['suggestions'][0]['suggested_order_units']);
        $this->assertSame(8000, $dashboard['inventory_intelligence']['ageing']['buckets']['active']['stock_value_minor']);
        $this->assertSame('FORECAST', $dashboard['cash_forecast']['label']);
        $this->assertFalse($dashboard['cash_forecast']['authoritative_finance_statement']);
        $this->assertSame(190, $dashboard['cash_forecast']['average_daily_inflow_minor']);
        $this->assertSame(0, $dashboard['cash_forecast']['average_daily_outflow_minor']);
        $this->assertSame(950, $dashboard['cash_forecast']['forecast_cash_position_minor']);
        $this->assertSame(0, $dashboard['cash_forecast']['cash_target_minor']);

        $saleMovements = StockMovement::where('branch_id', $this->branchA->id)
            ->where('source', StockMovement::SOURCE_SALE)
            ->where('source_id', $saleId)
            ->get();

        $this->assertCount(1, $saleMovements);
        $this->assertSame('-2.0000', (string) $saleMovements[0]->quantity);
        $this->assertSame('8.0000', (string) $product->stockLevel()->first()->quantity_on_hand);
    }

    public function test_cross_branch_reads_actions_and_references_are_rejected(): void
    {
        $unitA = $this->unit($this->branchA, 'ACC-SCOPE-A');
        $unitB = $this->unit($this->branchB, 'ACC-SCOPE-B');
        $productA = $this->stockedProduct($this->branchA, $unitA, 'ACC-SCOPE-PRODUCT-A', $this->adminA, '10');
        $productB = $this->stockedProduct($this->branchB, $unitB, 'ACC-SCOPE-PRODUCT-B', $this->adminB, '10');
        $serviceB = $this->workshopService($this->branchB, 'ACC-SCOPE-SERVICE-B');
        $customerA = $this->customer($this->branchA, 'Scope Customer A');
        $customerB = $this->customer($this->branchB, 'Scope Customer B');
        $jobA = $this->createJob($customerA, $this->adminA);
        $jobB = $this->createJob($customerB, $this->adminB);
        $invoiceB = $this->manualInvoice($this->branchB, $customerB, 1000, 'acc-scope-invoice-b');

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$jobA->id}/labour", [
                'workshop_service_id' => $serviceB->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('workshop_service_id');

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$jobA->id}/labour", [
                'description' => 'Inspection',
                'hours' => '1',
                'rate_minor' => 1000,
            ])
            ->assertCreated();
        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$jobA->id}/estimate")
            ->assertOk();
        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$jobA->id}/approve", [
                'approval_reference' => 'ACC-SCOPE-APPROVAL',
            ])
            ->assertOk();

        $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchA->id}/jobs/{$jobB->id}")
            ->assertNotFound();
        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchA->id}/jobs/{$jobA->id}")
            ->assertForbidden();
        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$jobA->id}/invoice", ['sale_id' => $invoiceB->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sale_id');

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes", [
                'reason' => 'Cross-branch reference attempt',
                'lines' => [[
                    'product_id' => $productB->id,
                    'unit_id' => $unitB->id,
                    'counted_quantity' => '10',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines');

        $transferPayload = [
            'destination_branch_id' => $this->branchB->id,
            'product_id' => $productA->id,
            'unit_id' => $unitA->id,
            'destination_product_id' => $productA->id,
            'destination_unit_id' => $unitA->id,
            'quantity' => '1',
            'reason' => 'Cross-branch reference attempt',
        ];

        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->branchA->id}/stock-transfers", $transferPayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('destination_product_id');

        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->branchA->id}/stock-transfers", [
                ...$transferPayload,
                'product_id' => $productB->id,
                'unit_id' => $unitB->id,
                'destination_product_id' => $productB->id,
                'destination_unit_id' => $unitB->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('source_product_id');

        $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchA->id}/reports/sales")
            ->assertOk();
        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchA->id}/reports/sales")
            ->assertForbidden();
        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchA->id}/dashboard/v2")
            ->assertForbidden();

        $this->assertNull($jobA->refresh()->sale_id);
        $this->assertSame(0, $jobA->partLines()->where('product_id', $productB->id)->count());
        $this->assertSame(0, Stocktake::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(0, StockMovement::where('branch_id', $this->branchA->id)->where('product_id', $productB->id)->count());
    }

    private function configureLoyalty(Branch $branch, array $overrides): void
    {
        $defaults = [
            'inactivity_days' => 90,
            'min_qualifying_minor' => 1000,
            'min_qualifying_sales' => 1,
            'loyalty_discount_percent' => 5,
            'combined_approval_threshold_percent' => 10,
        ];

        $this->withAuthToken($this->adminA)
            ->putJson("/api/branches/{$branch->id}/loyalty/config", array_merge($defaults, $overrides))
            ->assertOk();
    }

    private function createJob(Customer $customer, User $actor, ?int $technicianId = null): ServiceJob
    {
        $response = $this->withAuthToken($actor)
            ->postJson("/api/branches/{$customer->branch_id}/jobs", [
                'customer_id' => $customer->id,
                'vehicle_make' => 'Toyota',
                'vehicle_model' => 'Corolla',
                'vehicle_registration' => 'ACC-123',
                'vehicle_year' => '2018',
                'complaint' => 'Acceptance test complaint',
                'technician_id' => $technicianId,
            ])
            ->assertCreated();

        return ServiceJob::findOrFail($response->json('data.id'));
    }

    private function customer(Branch $branch, string $name): Customer
    {
        return Customer::create([
            'branch_id' => $branch->id,
            'name' => $name,
            'phone' => '077000'.rand(1000, 9999),
            'is_active' => true,
        ]);
    }

    private function discountQuote(Customer $customer, array $overrides): TestResponse
    {
        return $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/loyalty/discount/quote", array_merge([
                'customer_id' => $customer->id,
                'unit_price_minor' => 2000,
                'quantity' => 1,
                'floor_price_minor' => 0,
                'tax_rate_percent' => 0,
            ], $overrides));
    }

    private function manualInvoice(Branch $branch, Customer $customer, int $totalMinor, string $reference): Sale
    {
        return Sale::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'sale_number' => strtoupper($reference).'-'.Str::upper(Str::random(6)),
            'status' => 'posted',
            'payment_status' => 'unpaid',
            'currency_code' => $branch->currency_code,
            'subtotal_minor' => $totalMinor,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $totalMinor,
            'idempotency_key' => $reference.'-'.Str::uuid(),
            'request_hash' => hash('sha256', $reference),
        ]);
    }

    private function qualificationFor(Customer $customer): LoyaltyQualification
    {
        return LoyaltyQualification::where('branch_id', $customer->branch_id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();
    }

    private function recalculateLoyalty(Branch $branch): array
    {
        return $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$branch->id}/loyalty/recalculate")
            ->assertOk()
            ->json('data');
    }

    private function stockedProduct(Branch $branch, Unit $unit, string $sku, User $actor, string $quantity): Product
    {
        $product = $this->product($branch, $unit, null, $sku, 'Product '.$sku, null, 1000);

        if (bccomp($quantity, '0', 4) > 0) {
            app(StockLedger::class)->postOpening(
                $branch,
                $product,
                $quantity,
                $unit->id,
                'Acceptance test opening',
                $actor,
                'acc-opening-'.$sku,
            );
        }

        return $product->refresh();
    }

    private function workshopService(
        Branch $branch,
        string $code,
        string $hours = '1.00',
        int $rateMinor = 1000,
    ): WorkshopService {
        return WorkshopService::create([
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => 'Acceptance service',
            'description' => 'Acceptance test service',
            'default_hours' => $hours,
            'default_rate_minor' => $rateMinor,
            'currency_code' => $branch->currency_code,
            'is_active' => true,
        ]);
    }
}
