<?php

namespace Tests\Feature\Sales;

use App\Models\Branch;
use App\Models\MoneyMovement;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class SaleReturnApprovalTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    public function test_approving_a_return_restocks_once_refunds_once_and_replays_safely(): void
    {
        ['branch' => $branch, 'admin' => $admin, 'cashier' => $cashier, 'sale_id' => $saleId, 'sale_item_id' => $saleItemId, 'return_id' => $returnId, 'account' => $account, 'product' => $product] = $this->fixture();

        $this->withAuthToken($cashier)
            ->postJson("/api/branches/{$branch->id}/returns/{$returnId}/approve", [
                'payment_account_id' => $account->id,
            ])
            ->assertForbidden();

        $approved = $this->withAuthToken($admin)
            ->postJson("/api/branches/{$branch->id}/returns/{$returnId}/approve", [
                'payment_account_id' => $account->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.restock_decision', 'restocked')
            ->assertJsonPath('data.refund_status', 'refunded');

        $this->assertSame('10.0000', (string) $product->stockLevel()->first()->quantity_on_hand);
        $this->assertDatabaseHas('money_movements', [
            'branch_id' => $branch->id,
            'payment_account_id' => $account->id,
            'movement_type' => 'sale_return_refund',
            'direction' => 'out',
            'source' => 'sale_return',
            'source_id' => $returnId,
            'idempotency_key' => 'sale_return:'.$returnId.':refund',
            'amount_minor' => 2000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $branch->id,
            'action' => 'sale.return.approved',
            'entity_id' => $returnId,
        ]);

        $replay = $this->withAuthToken($admin)
            ->postJson("/api/branches/{$branch->id}/returns/{$returnId}/approve", [
                'payment_account_id' => $account->id,
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame($approved->json('data.id'), $replay['id']);
        $this->assertSame(1, MoneyMovement::where('source', 'sale_return')->where('source_id', $returnId)->count());
        $this->assertSame('10.0000', (string) $product->stockLevel()->first()->quantity_on_hand);
    }

    public function test_approving_a_processed_return_with_a_different_account_is_rejected(): void
    {
        ['branch' => $branch, 'admin' => $admin, 'return_id' => $returnId, 'account' => $account] = $this->fixture();

        $this->withAuthToken($admin)
            ->postJson("/api/branches/{$branch->id}/returns/{$returnId}/approve", [
                'payment_account_id' => $account->id,
            ])
            ->assertOk();

        $otherAccount = PaymentAccount::create([
            'branch_id' => $branch->id,
            'name' => 'Bank',
            'type' => 'bank',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $this->withAuthToken($admin)
            ->postJson("/api/branches/{$branch->id}/returns/{$returnId}/approve", [
                'payment_account_id' => $otherAccount->id,
            ])
            ->assertStatus(409);
    }

    public function test_return_idempotency_ignores_nested_key_order(): void
    {
        ['branch' => $branch, 'admin' => $admin, 'sale_id' => $saleId, 'sale_item_id' => $saleItemId] = $this->fixture(false);
        $key = (string) Str::uuid();

        $first = $this->withAuthToken($admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$branch->id}/sales/{$saleId}/returns", [
                'reason' => 'Wrong part',
                'items' => [
                    ['quantity' => '1', 'sale_item_id' => $saleItemId],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $replay = $this->withAuthToken($admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$branch->id}/sales/{$saleId}/returns", [
                'items' => [
                    ['sale_item_id' => $saleItemId, 'quantity' => '1'],
                ],
                'reason' => 'Wrong part',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $replay);
        $this->assertDatabaseCount('sale_returns', 1);
    }

    /** @return array{branch: Branch, admin: User, cashier: User, sale_id: string, sale_item_id: string, return_id: string, account: PaymentAccount, product: Product} */
    private function fixture(bool $withReturn = true): array
    {
        $platform = $this->platform();
        $branch = $this->branch($platform, 'RET');
        $admin = $this->user('Return Approver');
        $cashier = $this->user('Cashier');
        $this->membership($branch, $admin, 'admin');
        $this->membership($branch, $cashier, 'cashier');

        $unit = $this->unit($branch, 'PCS');
        $product = $this->product($branch, $unit, null, 'RET-1', 'Return Product', null, 1000);
        $product->stockLevel()->update(['quantity_on_hand' => '10.0000']);

        $sale = $this->withAuthToken($cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$branch->id}/checkout", [
                'lines' => [
                    ['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '2'],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 2000, 'currency_code' => 'USD'],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $returnId = '';
        if ($withReturn) {
            $return = $this->withAuthToken($admin)
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson("/api/branches/{$branch->id}/sales/{$sale['id']}/returns", [
                    'reason' => 'Damaged in transit',
                    'items' => [
                        ['sale_item_id' => $sale['items'][0]['id'], 'quantity' => '2'],
                    ],
                ])
                ->assertCreated()
                ->json('data');
            $returnId = (string) $return['id'];
        }

        $account = PaymentAccount::create([
            'branch_id' => $branch->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        return [
            'branch' => $branch,
            'admin' => $admin,
            'cashier' => $cashier,
            'sale_id' => (string) $sale['id'],
            'sale_item_id' => (string) $sale['items'][0]['id'],
            'return_id' => $returnId,
            'account' => $account,
            'product' => $product,
        ];
    }
}
