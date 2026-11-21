<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $platform = Platform::create(['name' => 'TMS', 'slug' => 'tms-'.uniqid(), 'currency_code' => 'USD']);
        $this->branch = Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Rusape Branch',
            'code' => 'TMS-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'rusape-'.uniqid(),
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);
        config(['catalog.public_branch_slug' => $this->branch->slug, 'app.url' => 'https://tazivamotorspares.example']);
        $this->category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Filters',
            'slug' => 'filters',
            'is_active' => true,
        ]);
    }

    private function product(string $name, string $slug, bool $isPublic = true, bool $isActive = true, ?string $branchId = null): Product
    {
        $branchId ??= $this->branch->id;
        $unit = Unit::create([
            'branch_id' => $branchId,
            'code' => 'EA-'.strtoupper(bin2hex(random_bytes(2))),
            'name' => 'Each',
            'allow_decimal' => false,
            'is_active' => true,
        ]);

        return Product::create([
            'branch_id' => $branchId,
            'category_id' => $branchId === $this->branch->id ? $this->category->id : null,
            'base_unit_id' => $unit->id,
            'sku' => strtoupper(str_replace('-', '', $slug)).'-'.strtoupper(bin2hex(random_bytes(2))),
            'name' => $name,
            'description' => 'PRIVATE INTERNAL DESCRIPTION MUST NOT BE PUBLIC',
            'selling_price_minor' => 1250,
            'currency_code' => 'USD',
            'track_stock' => true,
            'is_active' => $isActive,
            'public_slug' => $slug,
            'public_description' => $isPublic ? 'A customer-friendly description for this motor part.' : null,
            'is_public' => $isPublic,
        ]);
    }

    public function test_public_catalogue_lists_only_published_products_and_safe_fields(): void
    {
        $this->product('Air Filter', 'air-filter');
        $this->product('Internal Supplier Item', 'private-part', false);

        $this->get('/shop')->assertOk()
            ->assertSee('Air Filter')
            ->assertDontSee('Internal Supplier Item')
            ->assertDontSee('PRIVATE INTERNAL DESCRIPTION MUST NOT BE PUBLIC')
            ->assertDontSee('quantity_on_hand')
            ->assertSee('Contact our local team to confirm current availability');
    }

    public function test_product_page_has_canonical_metadata_and_accurate_structured_data_without_stock_claims(): void
    {
        $this->product('Air Filter', 'air-filter');

        $this->get('/shop/air-filter')->assertOk()
            ->assertSee('<h1>Air Filter</h1>', false)
            ->assertSee('rel="canonical" href="https://tazivamotorspares.example/shop/air-filter"', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"priceCurrency":"USD"', false)
            ->assertSee('"price":"12.50"', false)
            ->assertDontSee('"availability"', false)
            ->assertDontSee('PRIVATE INTERNAL DESCRIPTION MUST NOT BE PUBLIC');
    }

    public function test_search_pages_are_not_indexed_and_search_is_bounded(): void
    {
        $this->product('Air Filter', 'air-filter');

        $this->get('/shop?q=filter')->assertOk()->assertSee('noindex, follow', false)->assertSee('Air Filter');
        $this->get('/shop?q='.str_repeat('x', 81))->assertUnprocessable();
    }

    public function test_archived_products_return_gone_and_products_from_other_branches_are_hidden(): void
    {
        $this->product('Discontinued Filter', 'discontinued-filter', true, false);
        $otherPlatform = Platform::create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'currency_code' => 'USD']);
        $other = Branch::create([
            'platform_id' => $otherPlatform->id,
            'name' => 'Other Branch',
            'code' => 'OTH-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'other-'.uniqid(),
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);
        $this->product('Other Branch Part', 'other-branch-part', true, true, $other->id);

        $this->get('/shop/discontinued-filter')->assertStatus(410);
        $this->get('/shop/other-branch-part')->assertNotFound();
        $this->get('/shop')->assertDontSee('Other Branch Part');
    }

    public function test_sitemap_lists_published_catalogue_routes(): void
    {
        $this->product('Air Filter', 'air-filter');

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('https://tazivamotorspares.example/shop', false)
            ->assertSee('https://tazivamotorspares.example/shop/air-filter', false)
            ->assertSee('https://tazivamotorspares.example/shop/category/filters', false);
    }
}
