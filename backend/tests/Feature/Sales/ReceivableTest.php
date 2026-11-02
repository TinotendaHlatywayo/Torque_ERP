<?php

namespace Tests\Feature\Sales;

use App\Models\Branch;
use App\Models\MoneyMovement;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class ReceivableTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $cashier;

    private User $admin;

    private Product $product;

    private Unit $unit;

    private PaymentAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');

        $this->cashier = $this->user('Cashier');
        $this->admin = $this->user('Admin');

        $this->membership($this->branchA, $this->cashier, 'cashier');
        $this->membership($this->branchA, $this->admin, 'admin');

        $this->unit = $this->unit($this->branchA, 'PCS', true);
        $this->product = $this->product($this->branchA, $this->unit, null, 'REC-1', 'Receivable Product', null, 1000);
        $this->product->stockLevel()->update(['quantity_on_hand' => '10.0000']);

        $this->account = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
    }

    public function test_credit_sale_creates_unpaid_receivable(): void
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
                'due_date' => '2026-11-15',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.paid_minor', 0)
            ->assertJsonPath('data.total_minor', 2000)
            ->json('data.id');

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/receivables")
            ->assertOk()
            ->assertJsonPath('data.0.id', $saleId)
            ->assertJsonPath('data.0.outstanding_minor', 2000);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/receivables/ageing")
            ->assertOk();
    }

    public function test_partial_payment_updates_outstanding_and_status(): void
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

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/payments", [
                'payment_account_id' => $this->account->id,
                'amount_minor' => 500,
                'method' => 'cash',
                'currency_code' => 'USD',
                'reference' => 'partial-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'partial')
            ->assertJsonPath('data.paid_minor', 500);

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/payments", [
                'payment_account_id' => $this->account->id,
                'amount_minor' => 1500,
                'method' => 'cash',
                'currency_code' => 'USD',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.paid_minor', 2000);

        $this->assertDatabaseHas('money_movements', [
            'branch_id' => $this->branchA->id,
            'source' => 'receivable_collection',
            'source_id' => $saleId,
            'amount_minor' => 500,
        ]);

        $this->assertDatabaseHas('money_movements', [
            'branch_id' => $this->branchA->id,
            'source' => 'receivable_collection',
            'source_id' => $saleId,
            'amount_minor' => 1500,
        ]);
    }

    public function test_collection_cannot_exceed_outstanding(): void
    {
        $saleId = $this->withAuthToken($this->cashier)
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
            ->assertCreated()
            ->json('data.id');

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/payments", [
                'payment_account_id' => $this->account->id,
                'amount_minor' => 1500,
                'method' => 'cash',
                'currency_code' => 'USD',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_minor');
    }

    public function test_collection_is_idempotent(): void
    {
        $saleId = $this->withAuthToken($this->cashier)
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
            ->assertCreated()
            ->json('data.id');

        $key = (string) Str::uuid();
        $payload = [
            'payment_account_id' => $this->account->id,
            'amount_minor' => 400,
            'method' => 'cash',
            'currency_code' => 'USD',
        ];

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/payments", $payload)
            ->assertOk();

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/payments", $payload)
            ->assertOk();

        $this->assertSame(1, Payment::where('sale_id', $saleId)->count());
        $this->assertSame(1, MoneyMovement::where('source', 'receivable_collection')->where('source_id', $saleId)->count());
    }
}
