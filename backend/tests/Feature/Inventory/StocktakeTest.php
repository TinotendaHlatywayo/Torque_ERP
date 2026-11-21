<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\Unit;
use App\Models\User;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class StocktakeTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $viewer;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'STK-A');
        $this->branchB = $this->branch($this->platform, 'STK-B');
        $this->admin = $this->user('Stocktake Admin A');
        $this->viewer = $this->user('Stocktake Viewer A');
        $this->adminB = $this->user('Stocktake Admin B');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->viewer, 'viewer');
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_approval_posts_one_movement_per_variance_line_and_rejects_duplicate_approval(): void
    {
        $unit = $this->unit($this->branchA);
        $shortProduct = $this->stockedProduct($this->branchA, $unit, 'STK-SHORT', $this->admin);
        $excessProduct = $this->stockedProduct($this->branchA, $unit, 'STK-EXCESS', $this->admin);
        $exactProduct = $this->stockedProduct($this->branchA, $unit, 'STK-EXACT', $this->admin);

        $stocktake = $this->createStocktake([
            $this->line($shortProduct, $unit, '7', 'Damaged during handling'),
            $this->line($excessProduct, $unit, '12', 'Found during count'),
            $this->line($exactProduct, $unit, '10'),
        ]);

        $this->assertSame('draft', $stocktake['status']);
        $this->assertSame('-3.0000', $stocktake['lines'][0]['variance']);
        $this->assertSame('2.0000', $stocktake['lines'][1]['variance']);
        $this->assertSame('0.0000', $stocktake['lines'][2]['variance']);

        $reviewed = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktake['id']}/review")
            ->assertOk()
            ->json('data');

        $this->assertSame('reviewed', $reviewed['status']);
        $this->assertNotNull($reviewed['reviewed_at']);

        $approved = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktake['id']}/approve", [
                'approval_reference' => 'STK-APPROVAL-1',
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('approved', $approved['status']);
        $this->assertSame('STK-APPROVAL-1', $approved['approval_reference']);
        $this->assertNotNull($approved['lines'][0]['stock_movement_id']);
        $this->assertNotNull($approved['lines'][1]['stock_movement_id']);
        $this->assertNull($approved['lines'][2]['stock_movement_id']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktake['id']}/approve", [
                'approval_reference' => 'STK-APPROVAL-2',
            ])
            ->assertStatus(409);

        $shortProduct->refresh();
        $excessProduct->refresh();
        $exactProduct->refresh();

        $this->assertSame('7.0000', (string) $shortProduct->stockLevel()->first()->quantity_on_hand);
        $this->assertSame('12.0000', (string) $excessProduct->stockLevel()->first()->quantity_on_hand);
        $this->assertSame('10.0000', (string) $exactProduct->stockLevel()->first()->quantity_on_hand);

        $movements = StockMovement::where('branch_id', $this->branchA->id)
            ->where('source', StockMovement::SOURCE_STOCKTAKE)
            ->get();

        $this->assertCount(2, $movements);
        $this->assertTrue($movements->every(fn (StockMovement $movement): bool => $movement->movement_type === StockMovement::TYPE_CORRECTION));

        $this->assertSame(1, Stocktake::whereKey($stocktake['id'])->where('status', Stocktake::STATUS_APPROVED)->count());
    }

    public function test_variance_without_reason_is_rejected(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->stockedProduct($this->branchA, $unit, 'STK-NO-REASON', $this->admin);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes", [
                'reason' => 'Monthly count',
                'lines' => [$this->line($product, $unit, '9', '')],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines');
    }

    public function test_stocktake_permissions_and_branch_scope_are_enforced(): void
    {
        $unitA = $this->unit($this->branchA, 'STK-A-PCS');
        $unitB = $this->unit($this->branchB, 'STK-B-PCS');
        $productA = $this->stockedProduct($this->branchA, $unitA, 'STK-SCOPE-A', $this->admin);
        $productB = $this->stockedProduct($this->branchB, $unitB, 'STK-SCOPE-B', $this->adminB);
        $stocktake = $this->createStocktake([$this->line($productA, $unitA, '10')]);
        $stocktakeB = $this->createStocktakeFor($this->branchB, $this->adminB, [
            $this->line($productB, $unitB, '10'),
        ]);

        $this->withAuthToken($this->viewer)
            ->getJson("/api/branches/{$this->branchA->id}/stocktakes")
            ->assertOk();

        $this->withAuthToken($this->viewer)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes", [
                'reason' => 'Unauthorized count',
                'lines' => [$this->line($productA, $unitA, '10')],
            ])
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktake['id']}/review")
            ->assertOk();

        $this->withAuthToken($this->viewer)
            ->postJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktake['id']}/approve", [])
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktakeB['id']}")
            ->assertNotFound();

        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchA->id}/stocktakes/{$stocktake['id']}")
            ->assertForbidden();
    }

    private function createStocktake(array $lines): array
    {
        return $this->createStocktakeFor($this->branchA, $this->admin, $lines);
    }

    private function createStocktakeFor(Branch $branch, User $actor, array $lines): array
    {
        return $this->withAuthToken($actor)
            ->postJson("/api/branches/{$branch->id}/stocktakes", [
                'reason' => 'Monthly count',
                'lines' => $lines,
            ])
            ->assertCreated()
            ->json('data');
    }

    private function line(Product $product, Unit $unit, string $counted, ?string $reason = null): array
    {
        return [
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'counted_quantity' => $counted,
            'reason' => $reason,
        ];
    }

    private function stockedProduct(Branch $branch, Unit $unit, string $sku, User $actor): Product
    {
        $product = $this->product($branch, $unit, null, $sku, 'Product '.$sku, null, 1000);

        app(StockLedger::class)->postOpening(
            $branch,
            $product,
            '10',
            $unit->id,
            'Test opening',
            $actor,
            'stocktake-opening-'.$sku,
        );

        return $product->refresh();
    }
}
