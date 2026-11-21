<?php

namespace Tests\Feature\Sales;

use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class SameProductLineStockTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    public function test_two_lines_for_one_product_post_and_void_stock_together(): void
    {
        $platform = $this->platform();
        $branch = $this->branch($platform, 'DUP');
        $cashier = $this->user('Cashier');
        $manager = $this->user('Manager');
        $this->membership($branch, $cashier, 'cashier');
        $this->membership($branch, $manager, 'manager');

        $unit = $this->unit($branch, 'PCS');
        $product = $this->product($branch, $unit, null, 'DUP-1', 'Duplicate Product', null, 1000);
        $product->stockLevel()->update(['quantity_on_hand' => '10.0000']);

        $saleId = $this->withAuthToken($cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$branch->id}/checkout", [
                'lines' => [
                    ['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '2'],
                    ['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '3'],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 5000, 'currency_code' => 'USD'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.total_minor', 5000)
            ->assertJsonCount(2, 'data.items')
            ->json('data.id');

        $sale = Sale::findOrFail($saleId);
        $itemIds = $sale->items->pluck('id')->all();

        $this->assertSame(2, StockMovement::where('source', 'sale')->where('source_id', $saleId)->count());
        $this->assertStockBalance($branch->id, $product->id, '5.0000');

        foreach ($itemIds as $itemId) {
            $this->assertDatabaseHas('stock_movements', [
                'branch_id' => $branch->id,
                'idempotency_key' => 'sale:'.$saleId.':'.$itemId,
            ]);
        }

        $this->withAuthToken($manager)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$branch->id}/sales/{$saleId}/void", ['reason' => 'Duplicate lines'])
            ->assertOk()
            ->assertJsonPath('data.status', 'voided');

        $this->assertSame(2, StockMovement::where('movement_type', StockMovement::TYPE_CORRECTION)->count());
        $this->assertStockBalance($branch->id, $product->id, '10.0000');

        foreach ($itemIds as $itemId) {
            $this->assertDatabaseHas('stock_movements', [
                'branch_id' => $branch->id,
                'idempotency_key' => 'void:'.$saleId.':'.$itemId,
            ]);
        }
    }

    private function assertStockBalance(string $branchId, string $productId, string $expected): void
    {
        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $branchId,
            'product_id' => $productId,
            'quantity_on_hand' => $expected,
        ]);
    }
}
