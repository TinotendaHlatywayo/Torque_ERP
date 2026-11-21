<?php

namespace Tests\Feature\Sales;

use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class SaleCollectionIdempotencyTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    public function test_payment_key_race_returns_conflict_instead_of_server_error(): void
    {
        $platform = $this->platform();
        $branch = $this->branch($platform, 'COL');
        $cashier = $this->user('Cashier');
        $admin = $this->user('Admin');
        $this->membership($branch, $cashier, 'cashier');
        $this->membership($branch, $admin, 'admin');

        $unit = $this->unit($branch, 'PCS');
        $product = $this->product($branch, $unit, null, 'COL-1', 'Collection Product', null, 1000);
        $product->stockLevel()->update(['quantity_on_hand' => '10.0000']);

        $saleId = $this->withAuthToken($cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$branch->id}/checkout", [
                'lines' => [
                    ['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '2'],
                ],
                'payments' => [],
            ])
            ->assertCreated()
            ->json('data.id');

        $account = PaymentAccount::create([
            'branch_id' => $branch->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $key = (string) Str::uuid();

        Payment::creating(function () use ($branch, $saleId, $key): void {
            DB::table('payments')->insert([
                'id' => (string) Str::ulid(),
                'branch_id' => $branch->id,
                'sale_id' => $saleId,
                'method' => 'cash',
                'amount_minor' => 500,
                'currency_code' => 'USD',
                'status' => 'recorded',
                'idempotency_key' => $key,
                'request_hash' => str_repeat('0', 64),
                'actor_id' => null,
                'created_at' => now(),
            ]);
        });

        $this->withAuthToken($admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$branch->id}/sales/{$saleId}/payments", [
                'payment_account_id' => $account->id,
                'amount_minor' => 500,
                'method' => 'cash',
                'currency_code' => 'USD',
            ])
            ->assertStatus(409);

        $this->assertSame(0, Payment::where('branch_id', $branch->id)->count());
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'paid_minor' => 0,
            'payment_status' => 'unpaid',
        ]);
    }
}
