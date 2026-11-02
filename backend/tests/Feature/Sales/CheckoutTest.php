<?php

namespace Tests\Feature\Sales;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $cashier;

    private User $adminB;

    private Product $product;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->cashier = $this->user('Cashier');
        $this->adminB = $this->user('Branch B Admin');

        $this->membership($this->branchA, $this->cashier, 'cashier');
        $this->membership($this->branchB, $this->adminB, 'admin');

        $this->unit = $this->unit($this->branchA, 'PCS', true);
        $this->product = $this->product($this->branchA, $this->unit, null, 'CHECK-1', 'Checkout Product', null, 1000);
        $this->product->stockLevel()->update(['quantity_on_hand' => '10.0000']);
    }

    public function test_checkout_posts_sale_payment_receipt_and_stock_movement(): void
    {
        $key = (string) Str::uuid();

        $response = $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', $key)
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
                    ['method' => 'cash', 'amount_minor' => 2090, 'currency_code' => 'USD', 'reference' => 'drawer-1'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.total_minor', 2090)
            ->assertJsonPath('data.paid_minor', 2090)
            ->assertJsonPath('data.items.0.quantity', '2.0000')
            ->assertJsonPath('data.payments.0.method', 'cash')
            ->assertJsonPath('data.payments.0.reference', 'drawer-1');

        $saleId = $response->json('data.id');
        $receiptNumber = $response->json('data.receipt.receipt_number');

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'branch_id' => $this->branchA->id,
            'status' => 'posted',
            'payment_status' => 'paid',
            'total_minor' => 2090,
        ]);

        $this->assertDatabaseHas('receipts', [
            'sale_id' => $saleId,
            'branch_id' => $this->branchA->id,
            'receipt_number' => $receiptNumber,
        ]);

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => '8.0000',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'source' => 'sale',
            'source_id' => $saleId,
            'quantity' => '-2.0000',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $this->branchA->id,
            'action' => 'sale.posted',
            'entity_id' => $saleId,
        ]);
    }

    public function test_checkout_is_idempotent_for_retries(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                    'quantity' => '1',
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount_minor' => 1000, 'currency_code' => 'USD'],
            ],
        ];

        $first = $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/checkout", $payload)
            ->assertCreated()
            ->json('data.id');

        $second = $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/checkout", $payload)
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Sale::where('branch_id', $this->branchA->id)->count());
        $this->assertStockBalance('9.0000');
    }

    public function test_checkout_rejects_payment_total_mismatch(): void
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
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 1001, 'currency_code' => 'USD'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payments');

        $this->assertSame(0, Sale::where('branch_id', $this->branchA->id)->count());
        $this->assertStockBalance('10.0000');
    }

    public function test_checkout_rolls_back_when_stock_is_insufficient(): void
    {
        $this->product->stockLevel()->update(['quantity_on_hand' => '1.0000']);

        $this->withAuthToken($this->cashier)
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
            ->assertStatus(409);

        $this->assertSame(0, Sale::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(0, Receipt::where('branch_id', $this->branchA->id)->count());
        $this->assertStockBalance('1.0000');
    }

    public function test_split_payments_are_recorded(): void
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
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 400, 'currency_code' => 'USD'],
                    ['method' => 'card', 'amount_minor' => 600, 'currency_code' => 'USD', 'reference' => 'card-1'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payments.0.method', 'cash')
            ->assertJsonPath('data.payments.1.method', 'card');

        $this->assertSame(2, Payment::where('branch_id', $this->branchA->id)->count());
    }

    public function test_customer_and_sale_detail_are_branch_scoped(): void
    {
        $customer = Customer::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Sale Customer',
            'is_active' => true,
        ]);

        $saleId = $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'customer_id' => $customer->id,
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '1',
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 1000, 'currency_code' => 'USD'],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/sales/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.receipt.receipt_number', fn ($value) => is_string($value));

        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchB->id}/sales/{$saleId}")
            ->assertNotFound();
    }

    public function test_unauthenticated_and_forbidden_checkout_are_rejected(): void
    {
        $this->postJson("/api/branches/{$this->branchA->id}/checkout", [
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount_minor' => 1000, 'currency_code' => 'USD']],
        ])->assertUnauthorized();

        $viewer = $this->user('Viewer');
        $this->membership($this->branchA, $viewer, 'viewer');

        $this->withAuthToken($viewer)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => 1]],
                'payments' => [['method' => 'cash', 'amount_minor' => 1000, 'currency_code' => 'USD']],
            ])
            ->assertForbidden();
    }

    private function assertStockBalance(string $expected): void
    {
        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => $expected,
        ]);
    }
}
