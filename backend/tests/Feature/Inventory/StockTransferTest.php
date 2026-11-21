<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $source;

    private Branch $destination;

    private User $bothAdmin;

    private User $sourceAdmin;

    private User $destinationAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->source = $this->branch($this->platform, 'TRF-SRC');
        $this->destination = $this->branch($this->platform, 'TRF-DST');
        $this->source->update(['status' => Branch::STATUS_ACTIVE]);
        $this->destination->update(['status' => Branch::STATUS_ACTIVE]);
        $this->bothAdmin = $this->user('Transfer Both Admin');
        $this->sourceAdmin = $this->user('Transfer Source Admin');
        $this->destinationAdmin = $this->user('Transfer Destination Admin');

        $this->membership($this->source, $this->bothAdmin, 'admin');
        $this->membership($this->destination, $this->bothAdmin, 'admin');
        $this->membership($this->source, $this->sourceAdmin, 'admin');
        $this->membership($this->destination, $this->destinationAdmin, 'admin');
    }

    public function test_dispatch_moves_source_stock_and_receipt_moves_destination_stock(): void
    {
        $sourceUnit = $this->unit($this->source, 'SRC-PCS');
        $destinationUnit = $this->unit($this->destination, 'DST-PCS');
        $sourceProduct = $this->stockedProduct($this->source, $sourceUnit, 'TRF-SRC-PRODUCT', $this->bothAdmin);
        $destinationProduct = $this->stockedProduct($this->destination, $destinationUnit, 'TRF-DST-PRODUCT', $this->bothAdmin, '0');

        $transfer = $this->createTransfer($sourceProduct, $sourceUnit, $destinationProduct, $destinationUnit);
        $this->assertSame('draft', $transfer['status']);

        $dispatched = $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}/dispatch")
            ->assertOk()
            ->json('data');

        $this->assertSame('dispatched', $dispatched['status']);

        $sourceProduct->refresh();
        $this->assertSame('7.0000', (string) $sourceProduct->stockLevel()->first()->quantity_on_hand);

        $sourceMovements = StockMovement::where('branch_id', $this->source->id)
            ->where('source', StockMovement::SOURCE_STOCK_TRANSFER)
            ->where('source_id', $transfer['id'])
            ->get();

        $this->assertCount(1, $sourceMovements);
        $this->assertSame(StockMovement::TYPE_TRANSFER_OUT, $sourceMovements->first()->movement_type);
        $this->assertSame('-3.0000', (string) $sourceMovements->first()->quantity);

        $inTransit = $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}/in-transit")
            ->assertOk()
            ->json('data');

        $this->assertSame('in_transit', $inTransit['status']);

        $received = $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}/receive")
            ->assertOk()
            ->json('data');

        $this->assertSame('received', $received['status']);
        $this->assertNotNull($received['received_at']);

        $destinationProduct->refresh();
        $this->assertSame('3.0000', (string) $destinationProduct->stockLevel()->first()->quantity_on_hand);

        $destinationMovements = StockMovement::where('branch_id', $this->destination->id)
            ->where('source', StockMovement::SOURCE_STOCK_TRANSFER)
            ->where('source_id', $transfer['id'])
            ->get();

        $this->assertCount(1, $destinationMovements);
        $this->assertSame(StockMovement::TYPE_TRANSFER_IN, $destinationMovements->first()->movement_type);
        $this->assertSame('3.0000', (string) $destinationMovements->first()->quantity);
    }

    public function test_cancellation_reverses_only_source_stock_after_dispatch(): void
    {
        $sourceUnit = $this->unit($this->source, 'SRC-PCS');
        $destinationUnit = $this->unit($this->destination, 'DST-PCS');
        $sourceProduct = $this->stockedProduct($this->source, $sourceUnit, 'TRF-CANCEL-SRC', $this->bothAdmin);
        $destinationProduct = $this->stockedProduct($this->destination, $destinationUnit, 'TRF-CANCEL-DST', $this->bothAdmin, '0');

        $transfer = $this->createTransfer($sourceProduct, $sourceUnit, $destinationProduct, $destinationUnit, '4');

        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}/dispatch")
            ->assertOk();

        $cancelled = $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}/cancel", [
                'reason' => 'Customer cancelled the transfer',
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertNotNull($cancelled['cancellation_movement_id']);

        $sourceProduct->refresh();
        $destinationProduct->refresh();

        $this->assertSame('10.0000', (string) $sourceProduct->stockLevel()->first()->quantity_on_hand);
        $this->assertSame('0.0000', (string) $destinationProduct->stockLevel()->first()->quantity_on_hand);

        $reversal = StockMovement::findOrFail($cancelled['cancellation_movement_id']);

        $this->assertSame(StockMovement::TYPE_REVERSAL, $reversal->movement_type);
        $this->assertSame(StockMovement::SOURCE_REVERSAL, $reversal->source);
        $this->assertSame($cancelled['source_movement_id'], $reversal->reverses_movement_id);
        $this->assertSame('4.0000', (string) $reversal->quantity);
        $this->assertSame(0, StockMovement::where('branch_id', $this->destination->id)->where('source', StockMovement::SOURCE_STOCK_TRANSFER)->count());
    }

    public function test_draft_cancellation_does_not_post_any_movement(): void
    {
        $sourceUnit = $this->unit($this->source, 'SRC-PCS');
        $destinationUnit = $this->unit($this->destination, 'DST-PCS');
        $sourceProduct = $this->stockedProduct($this->source, $sourceUnit, 'TRF-DRAFT-SRC', $this->bothAdmin);
        $destinationProduct = $this->stockedProduct($this->destination, $destinationUnit, 'TRF-DRAFT-DST', $this->bothAdmin, '0');

        $transfer = $this->createTransfer($sourceProduct, $sourceUnit, $destinationProduct, $destinationUnit);

        $cancelled = $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}/cancel", [
                'reason' => 'No longer needed',
            ])
            ->assertOk()
            ->json('data');

        $this->assertNull($cancelled['cancellation_movement_id']);
        $this->assertSame(0, StockMovement::where('source', StockMovement::SOURCE_STOCK_TRANSFER)->count());
        $this->assertSame(0, StockMovement::where('movement_type', StockMovement::TYPE_REVERSAL)->count());
    }

    public function test_duplicate_receipt_is_rejected_and_does_not_post_twice(): void
    {
        $sourceUnit = $this->unit($this->source, 'SRC-PCS');
        $destinationUnit = $this->unit($this->destination, 'DST-PCS');
        $sourceProduct = $this->stockedProduct($this->source, $sourceUnit, 'TRF-DUPE-SRC', $this->bothAdmin);
        $destinationProduct = $this->stockedProduct($this->destination, $destinationUnit, 'TRF-DUPE-DST', $this->bothAdmin, '0');

        $transfer = $this->createTransfer($sourceProduct, $sourceUnit, $destinationProduct, $destinationUnit);

        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}/dispatch")
            ->assertOk();

        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}/receive")
            ->assertOk();

        $this->withAuthToken($this->bothAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}/receive")
            ->assertStatus(409);

        $destinationProduct->refresh();

        $this->assertSame('3.0000', (string) $destinationProduct->stockLevel()->first()->quantity_on_hand);
        $this->assertSame(1, StockMovement::where('branch_id', $this->destination->id)
            ->where('source', StockMovement::SOURCE_STOCK_TRANSFER)
            ->where('source_id', $transfer['id'])
            ->count());
    }

    public function test_cross_branch_authorization_is_enforced_on_both_branches(): void
    {
        $sourceUnit = $this->unit($this->source, 'SRC-PCS');
        $destinationUnit = $this->unit($this->destination, 'DST-PCS');
        $sourceProduct = $this->stockedProduct($this->source, $sourceUnit, 'TRF-AUTH-SRC', $this->bothAdmin);
        $destinationProduct = $this->stockedProduct($this->destination, $destinationUnit, 'TRF-AUTH-DST', $this->bothAdmin, '0');
        $payload = $this->transferPayload($sourceProduct, $sourceUnit, $destinationProduct, $destinationUnit);

        $this->withAuthToken($this->sourceAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers", $payload)
            ->assertForbidden();

        $this->withAuthToken($this->destinationAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers", $payload)
            ->assertForbidden();

        $transfer = $this->createTransfer($sourceProduct, $sourceUnit, $destinationProduct, $destinationUnit);

        $this->withAuthToken($this->destinationAdmin)
            ->getJson("/api/branches/{$this->source->id}/stock-transfers/{$transfer['id']}")
            ->assertForbidden();

        $this->withAuthToken($this->sourceAdmin)
            ->postJson("/api/branches/{$this->source->id}/stock-transfers", [
                ...$payload,
                'destination_branch_id' => $this->source->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('destination_branch_id');
    }

    public function test_transfers_are_scoped_to_the_route_branch(): void
    {
        $sourceUnit = $this->unit($this->source, 'SRC-PCS');
        $destinationUnit = $this->unit($this->destination, 'DST-PCS');
        $sourceProduct = $this->stockedProduct($this->source, $sourceUnit, 'TRF-SCOPE-SRC', $this->bothAdmin);
        $destinationProduct = $this->stockedProduct($this->destination, $destinationUnit, 'TRF-SCOPE-DST', $this->bothAdmin, '0');

        $transfer = $this->createTransfer($sourceProduct, $sourceUnit, $destinationProduct, $destinationUnit);

        $list = $this->withAuthToken($this->bothAdmin)
            ->getJson("/api/branches/{$this->source->id}/stock-transfers")
            ->assertOk()
            ->json('data');

        $this->assertSame([$transfer['id']], array_column($list, 'id'));

        $this->withAuthToken($this->bothAdmin)
            ->getJson("/api/branches/{$this->destination->id}/stock-transfers/{$transfer['id']}")
            ->assertNotFound();
    }

    private function createTransfer(
        Product $sourceProduct,
        Unit $sourceUnit,
        Product $destinationProduct,
        Unit $destinationUnit,
        string $quantity = '3',
    ): array {
        return $this->withAuthToken($this->bothAdmin)
            ->postJson(
                "/api/branches/{$this->source->id}/stock-transfers",
                $this->transferPayload($sourceProduct, $sourceUnit, $destinationProduct, $destinationUnit, $quantity),
            )
            ->assertCreated()
            ->json('data');
    }

    private function transferPayload(
        Product $sourceProduct,
        Unit $sourceUnit,
        Product $destinationProduct,
        Unit $destinationUnit,
        string $quantity = '3',
    ): array {
        return [
            'destination_branch_id' => $this->destination->id,
            'product_id' => $sourceProduct->id,
            'unit_id' => $sourceUnit->id,
            'destination_product_id' => $destinationProduct->id,
            'destination_unit_id' => $destinationUnit->id,
            'quantity' => $quantity,
            'reason' => 'Branch replenishment',
        ];
    }

    private function stockedProduct(Branch $branch, Unit $unit, string $sku, User $actor, string $quantity = '10'): Product
    {
        $product = $this->product($branch, $unit, null, $sku, 'Product '.$sku, null, 1000);

        if (bccomp($quantity, '0', 4) > 0) {
            app(StockLedger::class)->postOpening(
                $branch,
                $product,
                $quantity,
                $unit->id,
                'Test opening',
                $actor,
                'transfer-opening-'.$sku,
            );
        }

        return $product->refresh();
    }
}
