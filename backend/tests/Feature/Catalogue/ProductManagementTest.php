<?php

namespace Tests\Feature\Catalogue;

use App\Models\Branch;
use App\Models\CmsMedia;
use App\Models\Platform;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');
        $this->admin = $this->user('Branch A Admin');
        $this->membership($this->branchA, $this->admin, 'admin');
    }

    public function test_product_is_created_with_price_in_minor_units_and_zero_stock(): void
    {
        $unit = $this->unit($this->branchA, 'PCS');
        $category = $this->category($this->branchA, 'Filters');

        $response = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Oil Filter',
                'sku' => 'of-100',
                'barcode' => ' 1234567890 ',
                'description' => 'Spin-on oil filter',
                'category_id' => $category->id,
                'base_unit_id' => $unit->id,
                'selling_price' => '12.34',
                'track_stock' => true,
                'reorder_level' => '2',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'OF-100')
            ->assertJsonPath('data.barcode', '1234567890')
            ->assertJsonPath('data.selling_price_minor', 1234)
            ->assertJsonPath('data.selling_price', '12.34')
            ->assertJsonPath('data.currency_code', 'USD')
            ->assertJsonPath('data.stock.quantity_on_hand', '0.0000')
            ->assertJsonPath('data.stock.reorder_level', '2.0000')
            ->assertJsonPath('data.stock.is_low', true);

        $product = Product::where('sku', 'OF-100')->firstOrFail();

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 0,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $this->branchA->id,
            'action' => 'product.created',
            'entity_id' => $product->id,
        ]);

        $response->assertJsonPath('data.category.name', 'Filters');
        $this->assertSame($category->id, $response->json('data.category.id'));
    }

    public function test_products_require_customer_facing_fields_before_publication(): void
    {
        $unit = $this->unit($this->branchA, 'WEB');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Air Filter',
                'sku' => 'WEB-100',
                'base_unit_id' => $unit->id,
                'selling_price' => '12.50',
                'is_public' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_public');

        $response = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Air Filter',
                'sku' => 'WEB-101',
                'base_unit_id' => $unit->id,
                'selling_price' => '12.50',
                'is_public' => true,
                'public_slug' => 'air-filter',
                'public_description' => 'A customer-friendly part description.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_public', true)
            ->assertJsonPath('data.public_slug', 'air-filter')
            ->assertJsonPath('data.public_description', 'A customer-friendly part description.');

        $this->assertDatabaseHas('products', [
            'id' => $response->json('data.id'),
            'branch_id' => $this->branchA->id,
            'is_public' => true,
        ]);
    }

    public function test_public_product_can_store_an_exact_image_url_and_description(): void
    {
        $unit = $this->unit($this->branchA, 'WEB');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Oil Filter',
                'sku' => 'WEB-IMG-100',
                'base_unit_id' => $unit->id,
                'selling_price' => '12.50',
                'is_public' => true,
                'public_slug' => 'oil-filter-image',
                'public_description' => 'A customer-friendly oil filter description.',
                'public_image_url' => '/images/products/oil-filter.jpg',
                'public_image_alt' => 'Photo of an oil filter',
            ])
            ->assertCreated()
            ->assertJsonPath('data.public_image_url', '/images/products/oil-filter.jpg')
            ->assertJsonPath('data.public_image_alt', 'Photo of an oil filter')
            ->assertJsonPath('data.public_description', 'A customer-friendly oil filter description.');

        $this->assertDatabaseHas('products', [
            'branch_id' => $this->branchA->id,
            'sku' => 'WEB-IMG-100',
            'public_image_url' => '/images/products/oil-filter.jpg',
        ]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Unsafe Image',
                'sku' => 'WEB-IMG-101',
                'base_unit_id' => $unit->id,
                'selling_price' => '12.50',
                'is_public' => true,
                'public_slug' => 'unsafe-image',
                'public_description' => 'A customer-friendly description.',
                'public_image_url' => 'javascript:alert(1)',
                'public_image_alt' => 'Unsafe image',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('public_image_url');
    }

    public function test_duplicate_sku_is_rejected_case_insensitively(): void
    {
        $unit = $this->unit($this->branchA);
        $this->product($this->branchA, $unit, null, 'ABC-1');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Duplicate SKU',
                'sku' => 'abc-1',
                'base_unit_id' => $unit->id,
                'selling_price' => '1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_duplicate_barcode_is_rejected_but_multiple_null_barcodes_are_allowed(): void
    {
        $unit = $this->unit($this->branchA);
        $first = $this->product($this->branchA, $unit, null, 'A-1', 'First', '555000');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Duplicate Barcode',
                'sku' => 'A-2',
                'barcode' => '555000',
                'base_unit_id' => $unit->id,
                'selling_price' => '1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('barcode');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'No Barcode One',
                'sku' => 'A-3',
                'base_unit_id' => $unit->id,
                'selling_price' => '1.00',
            ])
            ->assertCreated();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'No Barcode Two',
                'sku' => 'A-4',
                'base_unit_id' => $unit->id,
                'selling_price' => '1.00',
            ])
            ->assertCreated();

        $this->assertSame(1, Product::where('branch_id', $this->branchA->id)->where('barcode', '555000')->count());
        $this->assertSame(2, Product::where('branch_id', $this->branchA->id)->whereNull('barcode')->count());
        $this->assertNotNull($first);
    }

    public function test_sku_and_barcode_uniqueness_is_scoped_to_the_branch(): void
    {
        $unitA = $this->unit($this->branchA);
        $unitB = $this->unit($this->branchB);

        $this->product($this->branchA, $unitA, null, 'SHARED-1', 'Branch A', '777777');
        $this->product($this->branchB, $unitB, null, 'SHARED-1', 'Branch B', '777777');

        $this->assertDatabaseHas('products', [
            'branch_id' => $this->branchA->id,
            'sku' => 'SHARED-1',
            'barcode' => '777777',
        ]);
        $this->assertDatabaseHas('products', [
            'branch_id' => $this->branchB->id,
            'sku' => 'SHARED-1',
            'barcode' => '777777',
        ]);
    }

    public function test_category_and_unit_must_belong_to_the_same_branch(): void
    {
        $unitB = $this->unit($this->branchB);
        $categoryB = $this->category($this->branchB);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Cross Branch Product',
                'sku' => 'CROSS-1',
                'category_id' => $categoryB->id,
                'base_unit_id' => $unitB->id,
                'selling_price' => '1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'base_unit_id']);
    }

    public function test_archived_category_and_unit_cannot_be_used_for_a_new_product(): void
    {
        $unit = $this->unit($this->branchA);
        $category = $this->category($this->branchA);
        $category->update(['is_active' => false]);
        $unit->update(['is_active' => false]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Archived References',
                'sku' => 'ARCH-1',
                'category_id' => $category->id,
                'base_unit_id' => $unit->id,
                'selling_price' => '1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'base_unit_id']);
    }

    public function test_product_can_be_updated_and_prices_retain_currency_precision(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit, null, 'EDIT-1', 'Original', null, 1000);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/products/{$product->id}", [
                'name' => 'Updated Product',
                'selling_price' => '20.05',
                'reorder_level' => '7.5',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Product')
            ->assertJsonPath('data.selling_price_minor', 2005)
            ->assertJsonPath('data.selling_price', '20.05')
            ->assertJsonPath('data.stock.reorder_level', '7.5000')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.updated',
            'entity_id' => $product->id,
        ]);
    }

    public function test_product_archive_hides_it_by_default_and_all_status_returns_it(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit, null, 'ARCHIVE-1');

        $this->withAuthToken($this->admin)
            ->deleteJson("/api/branches/{$this->branchA->id}/products/{$product->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/products?status=all")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_base_unit_cannot_change_while_alternate_units_exist(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'BASE-1');
        $this->alternateUnit($product, $box);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/products/{$product->id}", [
                'base_unit_id' => $box->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('base_unit_id');
    }

    public function test_base_unit_cannot_change_while_stock_is_non_zero(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $each = $this->unit($this->branchA, 'EACH');
        $product = $this->product($this->branchA, $pcs, null, 'BASE-STOCK');
        $product->stockLevel()->update(['quantity_on_hand' => '3.0000']);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/products/{$product->id}", [
                'base_unit_id' => $each->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('base_unit_id');
    }

    public function test_base_unit_can_change_before_alternate_units_or_stock_exist(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $each = $this->unit($this->branchA, 'EACH');
        $product = $this->product($this->branchA, $pcs, null, 'BASE-2');

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/products/{$product->id}", [
                'base_unit_id' => $each->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.base_unit.id', $each->id);
    }

    public function test_non_stock_product_still_has_a_zero_balance_foundation_without_low_stock_flag(): void
    {
        $unit = $this->unit($this->branchA);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Service Consumable',
                'sku' => 'SERV-1',
                'base_unit_id' => $unit->id,
                'selling_price' => '0.00',
                'track_stock' => false,
                'reorder_level' => '5',
            ])
            ->assertCreated()
            ->assertJsonPath('data.track_stock', false)
            ->assertJsonPath('data.stock.quantity_on_hand', '0.0000')
            ->assertJsonPath('data.stock.is_low', false);
    }

    public function test_price_precision_beyond_currency_exponent_is_rejected(): void
    {
        $unit = $this->unit($this->branchA);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Too Precise',
                'sku' => 'PRICE-1',
                'base_unit_id' => $unit->id,
                'selling_price' => '1.234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('selling_price');
    }

    public function test_branch_user_can_upload_and_preview_a_converted_product_image(): void
    {
        Storage::fake('local');

        $response = $this->withAuthToken($this->admin)
            ->post("/api/branches/{$this->branchA->id}/products/media", [
                'image' => UploadedFile::fake()->image('brake-pads.png', 900, 600),
            ])
            ->assertCreated();

        $media = CmsMedia::findOrFail($response->json('data.id'));
        $this->assertNotNull($media->preferred_variant);
        $this->assertArrayHasKey($media->preferred_variant, $media->variants ?? []);
        $response->assertJsonPath('data.id', $media->id)
            ->assertJsonPath('data.preferred_variant', $media->preferred_variant);

        $variant = str_replace('_', '-', (string) $media->preferred_variant);
        $this->withAuthToken($this->admin)
            ->get("/api/branches/{$this->branchA->id}/products/media/{$media->id}/preview/{$variant}")
            ->assertOk();
    }
}
