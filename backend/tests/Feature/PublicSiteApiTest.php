<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsPageVersion;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use App\Support\PaynowHash;
use App\Support\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicSiteApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    private Product $product;

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
        config([
            'catalog.public_branch_slug' => $this->branch->slug,
            'catalog.reservation_expiration_minutes' => 15,
            'app.url' => 'https://store.example.test',
            'paynow.enabled' => false,
        ]);
        $this->category = Category::create(['branch_id' => $this->branch->id, 'name' => 'Filters', 'slug' => 'filters', 'is_active' => true]);
        $this->product = $this->product('Air Filter', 'air-filter');
    }

    public function test_site_summary_returns_branch_categories_and_featured_public_products(): void
    {
        $this->product('Oil Filter', 'oil-filter');
        $this->product('Cabin Filter', 'cabin-filter');
        $this->product('Fuel Filter', 'fuel-filter');
        $this->product('Hydraulic Filter', 'hydraulic-filter');
        $this->product('Internal Only', 'internal-only', ['is_public' => false, 'public_description' => null]);

        $response = $this->getJson('/api/public/site')->assertOk();

        $response->assertJsonPath('branch.name', 'Rusape Branch')
            ->assertJsonPath('branch.code', $this->branch->code)
            ->assertJsonPath('branch.timezone', 'Africa/Harare')
            ->assertJsonPath('branch.currency_code', 'USD')
            ->assertJsonPath('categories.0.name', 'Filters')
            ->assertJsonPath('categories.0.slug', 'filters')
            ->assertJsonPath('reservation_minutes', 15);
        $this->assertCount(4, $response->json('featured'));
        $this->assertSame(
            ['id', 'name', 'slug', 'category_name', 'category_slug', 'short_description', 'price', 'price_value', 'currency_code', 'image_url', 'image_alt', 'can_reserve'],
            array_keys($response->json('featured.0')),
        );
        $this->assertStringNotContainsString('Internal Only', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE INTERNAL DESCRIPTION', $response->getContent());
    }

    public function test_storefront_falls_back_to_an_active_branch_when_configured_slug_is_missing(): void
    {
        $this->branch->update(['slug' => 'renamed-'.uniqid()]);

        $this->getJson('/api/public/site')->assertOk()
            ->assertJsonPath('branch.name', 'Rusape Branch')
            ->assertJsonPath('catalogue_ready', true);
    }

    public function test_storefront_loads_empty_state_when_no_active_branch_exists(): void
    {
        $this->branch->update(['status' => Branch::STATUS_ARCHIVED]);

        $this->getJson('/api/public/site')->assertOk()
            ->assertJsonPath('catalogue_ready', false)
            ->assertJsonPath('categories', [])
            ->assertJsonPath('featured', []);

        $this->getJson('/api/public/products')->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('categories', []);
    }

    public function test_catalogue_returns_safe_fields_with_search_category_and_page_bounds(): void
    {
        $this->product('Oil Filter', 'oil-filter');
        $this->product('Brake Pad', 'brake-pad', [], $this->category('Brakes', 'brakes'));
        $this->product('Hidden Part', 'hidden-part', ['is_public' => false, 'public_description' => null]);

        $response = $this->getJson('/api/public/products?q=filter&category=filters&per_page=1&page=1')->assertOk();

        $response->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
        $this->assertSame('Air Filter', $response->json('data.0.name'));
        $this->assertSame(
            ['id', 'name', 'slug', 'category_name', 'category_slug', 'short_description', 'price', 'price_value', 'currency_code', 'image_url', 'image_alt', 'can_reserve'],
            array_keys($response->json('data.0')),
        );
        $this->assertSame('12.50', $response->json('data.0.price'));
        $this->assertSame(1250, $response->json('data.0.price_value'));
        $this->assertTrue($response->json('data.0.can_reserve'));
        $this->assertStringNotContainsString('PRIVATE INTERNAL DESCRIPTION', $response->getContent());
        $this->assertStringNotContainsString('quantity_on_hand', $response->getContent());
        $this->assertStringNotContainsString('selling_price_minor', $response->getContent());

        $this->getJson('/api/public/products?category=brakes')->assertOk()
            ->assertJsonPath('data.0.slug', 'brake-pad')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/public/products?category=missing')->assertNotFound();
        $this->getJson('/api/public/products?q='.str_repeat('x', 81))->assertUnprocessable()
            ->assertJsonValidationErrors('q');
        $this->getJson('/api/public/products?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson('/api/public/products?per_page=99')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_product_detail_returns_public_description_and_gone_or_not_found_for_hidden_products(): void
    {
        $related = $this->product('Oil Filter', 'oil-filter');
        $this->product('Hidden Part', 'hidden-part', ['is_public' => false, 'public_description' => null]);
        $this->product('Archived Part', 'archived-part', ['is_active' => false]);
        $inactiveCategory = $this->category('Old Stock', 'old-stock', false);
        $this->product('Inactive Category Part', 'inactive-category-part', [], $inactiveCategory);
        $otherBranch = $this->otherBranch();
        $this->product('Other Branch Part', 'other-branch-part', [], null, $otherBranch);

        $response = $this->getJson('/api/public/products/air-filter')->assertOk();

        $response->assertJsonPath('product.slug', 'air-filter')
            ->assertJsonPath('product.public_description', 'Customer facing description.')
            ->assertJsonPath('can_reserve', true);
        $this->assertSame('oil-filter', $response->json('related.0.slug'));
        $this->assertSame($related->name, $response->json('related.0.name'));
        $this->assertStringNotContainsString('PRIVATE INTERNAL DESCRIPTION', $response->getContent());
        $this->getJson('/api/public/products/hidden-part')->assertNotFound();
        $this->getJson('/api/public/products/other-branch-part')->assertNotFound();
        $this->getJson('/api/public/products/archived-part')->assertStatus(410);
        $this->getJson('/api/public/products/inactive-category-part')->assertStatus(410);
    }

    public function test_cms_page_returns_only_published_version_sections(): void
    {
        $user = User::factory()->create();
        $media = CmsMedia::create([
            'original_name' => 'workbench.png',
            'original_path' => 'cms/workbench/original.png',
            'mime_type' => 'image/png',
            'file_size' => 100,
            'width' => 960,
            'height' => 640,
            'variants' => ['960_webp' => ['path' => 'cms/workbench/960.webp', 'mime' => 'image/webp', 'width' => 960, 'height' => 640]],
            'uploaded_by' => $user->id,
        ]);
        $page = CmsPage::create([
            'title' => 'About Taziva',
            'slug' => 'about-taziva',
            'current_version' => 2,
            'published_version' => 1,
            'published_slug' => 'about-taziva',
            'created_by' => $user->id,
        ]);
        CmsPageVersion::create([
            'cms_page_id' => $page->id,
            'version' => 1,
            'title' => 'About Taziva',
            'slug' => 'about-taziva',
            'sections' => [
                ['type' => 'hero', 'heading' => 'Local parts', 'body' => 'Published body', 'media_id' => null, 'alt' => ''],
                ['type' => 'image', 'heading' => '', 'body' => '', 'media_id' => $media->id, 'alt' => 'Parts on a workbench'],
            ],
            'media_ids' => [$media->id],
            'created_by' => $user->id,
        ]);
        CmsPageVersion::create([
            'cms_page_id' => $page->id,
            'version' => 2,
            'title' => 'About Taziva',
            'slug' => 'about-taziva',
            'sections' => [['type' => 'text', 'heading' => 'Draft', 'body' => 'Unpublished body', 'media_id' => null, 'alt' => '']],
            'media_ids' => [],
            'created_by' => $user->id,
        ]);
        $draft = CmsPage::create(['title' => 'Draft', 'slug' => 'draft-page', 'current_version' => 1, 'created_by' => $user->id]);
        CmsPageVersion::create([
            'cms_page_id' => $draft->id,
            'version' => 1,
            'title' => 'Draft',
            'slug' => 'draft-page',
            'sections' => [['type' => 'text', 'heading' => 'Draft', 'body' => 'Not live', 'media_id' => null, 'alt' => '']],
            'media_ids' => [],
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/pages/about-taziva')->assertOk();

        $response->assertJsonPath('page.title', 'About Taziva')
            ->assertJsonPath('page.slug', 'about-taziva')
            ->assertJsonPath('page.sections.0.body', 'Published body')
            ->assertJsonPath('page.sections.1.alt', 'Parts on a workbench');
        $this->assertSame(['type', 'heading', 'body', 'image_url', 'alt'], array_keys($response->json('page.sections.1')));
        $this->assertStringContainsString('/api/media/'.$media->id.'/960-webp', $response->json('page.sections.1.image_url'));
        $this->assertStringNotContainsString('Unpublished body', $response->getContent());
        $this->getJson('/api/public/pages/draft-page')->assertNotFound();
    }

    public function test_reservation_start_returns_idempotency_key_without_stock_quantity(): void
    {
        $response = $this->postJson('/api/public/reservations/start', ['product_slug' => 'air-filter'])->assertOk();

        $response->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertTrue(Str::isUuid($response->json('idempotency_key')));
        $this->assertSame(['slug', 'name', 'price', 'currency_code', 'can_reserve'], array_keys($response->json('product')));
        $response->assertJsonPath('product.price', '12.50')
            ->assertJsonPath('product.can_reserve', true);
        $this->assertStringNotContainsString('quantity_on_hand', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE INTERNAL DESCRIPTION', $response->getContent());

        $this->product('No Stock Filter', 'no-stock-filter', [], $this->category, null, '0');
        $this->product('Hidden Part', 'hidden-part', ['is_public' => false, 'public_description' => null]);
        $this->postJson('/api/public/reservations/start', ['product_slug' => 'no-stock-filter'])->assertNotFound();
        $this->postJson('/api/public/reservations/start', ['product_slug' => 'hidden-part'])->assertNotFound();
    }

    public function test_reservation_create_returns_201_without_customer_details(): void
    {
        $payload = [
            'product_slug' => 'air-filter',
            'idempotency_key' => (string) Str::uuid(),
            'name' => 'Private Customer',
            'email' => 'private@example.test',
            'quantity' => '2',
        ];

        $response = $this->postJson('/api/public/reservations', $payload)->assertCreated();

        $response->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response->json('token'));
        $this->assertStringEndsWith($response->json('token'), $response->json('status_url'));
        $this->assertNull($response->json('payment_url'));
        $this->assertSame(['number', 'status', 'amount', 'currency_code', 'expires_at'], array_keys($response->json('reservation')));
        $response->assertJsonPath('reservation.status', 'awaiting_payment')
            ->assertJsonPath('reservation.amount', '25.00');
        $reservation = Reservation::firstOrFail();
        $response->assertJsonPath('reservation.number', $reservation->reservation_number);
        $this->assertSame('2.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
        $this->assertNotSame('Private Customer', $reservation->getRawOriginal('customer_name'));
        $this->assertStringNotContainsString('Private Customer', $response->getContent());
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
        $this->assertStringNotContainsString('customer_name', $response->getContent());
        $this->assertStringNotContainsString('quantity_on_hand', $response->getContent());
    }

    public function test_reservation_replay_reuses_the_hold_and_changed_payload_returns_422(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'product_slug' => 'air-filter',
            'idempotency_key' => $key,
            'name' => 'Replay Customer',
            'phone' => '+263771234567',
            'quantity' => '2',
        ];

        $first = $this->postJson('/api/public/reservations', $payload)->assertCreated();
        $replay = $this->postJson('/api/public/reservations', $payload)->assertCreated();
        $changed = $this->postJson('/api/public/reservations', [...$payload, 'quantity' => '1'])->assertUnprocessable();

        $this->assertSame($first->json('token'), $replay->json('token'));
        $this->assertSame(1, Reservation::count());
        $this->assertSame('2.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
        $changed->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonValidationErrors('quantity')
            ->assertJsonPath('errors.quantity.0', 'This idempotency key has already been used for a different reservation.');
    }

    public function test_reservation_status_and_cancel_expose_no_pii_and_release_stock_once(): void
    {
        $key = (string) Str::uuid();
        $created = $this->postJson('/api/public/reservations', [
            'product_slug' => 'air-filter',
            'idempotency_key' => $key,
            'name' => 'Status Customer',
            'phone' => '+263771234567',
            'quantity' => '2',
        ])->assertCreated();
        $token = $created->json('token');

        $status = $this->getJson('/api/public/reservations/'.$token)->assertOk();
        $status->assertJsonPath('reservation.product_name', 'Air Filter')
            ->assertJsonPath('reservation.quantity', '2')
            ->assertJsonPath('reservation.amount', '25.00')
            ->assertJsonPath('reservation.status', 'awaiting_payment')
            ->assertJsonPath('reservation.can_cancel', true);
        $this->assertSame(['number', 'product_name', 'quantity', 'amount', 'currency_code', 'status', 'payment_status', 'expires_at', 'can_cancel'], array_keys($status->json('reservation')));

        $cancelled = $this->postJson('/api/public/reservations/'.$token.'/cancel')->assertOk();
        $cancelled->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertJsonPath('reservation.status', 'cancelled')
            ->assertJsonPath('reservation.can_cancel', false)
            ->assertJsonPath('notice', 'Your reservation was cancelled and its stock hold was released.');
        $this->assertSame('0.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
        $this->postJson('/api/public/reservations/'.$token.'/cancel')->assertOk();
        $this->assertSame('0.0000', (string) StockLevel::firstOrFail()->quantity_reserved);

        $expiring = app(ReservationService::class)->create($this->branch, $this->product, [
            'quantity' => '1', 'name' => 'Expiring Customer', 'phone' => '123',
        ], (string) Str::uuid());
        $expiring['reservation']->update(['expires_at' => now()->subSecond()]);
        $expired = $this->getJson('/api/public/reservations/'.$expiring['token'])->assertOk();
        $expired->assertJsonPath('reservation.status', 'expired')
            ->assertJsonPath('reservation.can_cancel', false);
        $this->assertStringNotContainsString('Status Customer', $status->getContent());
        $this->assertStringNotContainsString('Expiring Customer', $expired->getContent());
    }

    public function test_reservation_create_checks_paynow_readiness_and_returns_payment_url(): void
    {
        $key = 'integration-secret-for-tests';
        config([
            'paynow.enabled' => true,
            'paynow.integration_id' => '12345',
            'paynow.integration_key' => $key,
            'paynow.currency' => 'USD',
            'app.url' => 'http://localhost:8000',
        ]);
        $payload = [
            'product_slug' => 'air-filter',
            'idempotency_key' => (string) Str::uuid(),
            'name' => 'Paynow Customer',
            'phone' => '+263771234567',
            'quantity' => '1',
        ];
        $this->postJson('/api/public/reservations', $payload)->assertStatus(503)->assertHeader('Cache-Control', 'no-store, private');

        config(['app.url' => 'https://store.example.test']);
        Http::preventStrayRequests();
        Http::fake([
            'https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(http_build_query($this->signed(['status' => 'Ok', 'browserurl' => 'https://www.paynow.co.zw/Payment/ConfirmPayment/123', 'pollurl' => 'https://www.paynow.co.zw/Interface/CheckPayment/?guid=test-guid'], $key)), 200),
        ]);

        $response = $this->postJson('/api/public/reservations', $payload)->assertCreated();

        $response->assertJsonPath('payment_url', 'https://www.paynow.co.zw/Payment/ConfirmPayment/123')
            ->assertJsonPath('notice', 'Continue to Paynow to complete payment for your reservation.');
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/interface/initiatetransaction')
            && PaynowHash::valid($request->data(), $key));
    }

    private function category(string $name, string $slug, bool $isActive = true): Category
    {
        return Category::create(['branch_id' => $this->branch->id, 'name' => $name, 'slug' => $slug, 'is_active' => $isActive]);
    }

    private function product(string $name, string $slug, array $overrides = [], ?Category $category = null, ?Branch $branch = null, string $onHand = '5'): Product
    {
        $branch ??= $this->branch;
        $category ??= $branch->id === $this->branch->id ? $this->category : null;
        $unit = Unit::create([
            'branch_id' => $branch->id,
            'code' => 'EA-'.strtoupper(bin2hex(random_bytes(4))),
            'name' => 'Each',
            'allow_decimal' => false,
            'is_active' => true,
        ]);

        $product = Product::create(array_merge([
            'branch_id' => $branch->id,
            'category_id' => $category?->id,
            'base_unit_id' => $unit->id,
            'sku' => strtoupper(str_replace('-', '', $slug)).'-'.strtoupper(bin2hex(random_bytes(4))),
            'name' => $name,
            'description' => 'PRIVATE INTERNAL DESCRIPTION MUST NOT BE PUBLIC',
            'selling_price_minor' => 1250,
            'currency_code' => 'USD',
            'track_stock' => true,
            'is_active' => true,
            'public_slug' => $slug,
            'public_description' => 'Customer facing description.',
            'is_public' => true,
        ], $overrides));

        $this->stock($product, $onHand);

        return $product;
    }

    private function stock(Product $product, string $onHand): StockLevel
    {
        return StockLevel::create([
            'branch_id' => $product->branch_id,
            'product_id' => $product->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => '0',
            'reorder_level' => '0',
        ]);
    }

    private function otherBranch(): Branch
    {
        $platform = Platform::create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'currency_code' => 'USD']);

        return Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Other Branch',
            'code' => 'OTH-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'other-'.uniqid(),
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);
    }

    private function signed(array $values, string $key): array
    {
        return [...$values, 'hash' => PaynowHash::make($values, $key)];
    }
}
