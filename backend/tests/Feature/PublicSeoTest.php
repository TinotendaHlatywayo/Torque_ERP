<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsPageVersion;
use App\Models\Platform;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use App\Support\ReservationService;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_URL = 'https://tazivamotorspares.example';

    private Branch $branch;

    private Category $category;

    private Product $product;

    private CmsPage $cmsPage;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => self::SITE_URL,
            'app.frontend_url' => self::SITE_URL,
            'app.env' => 'production',
            'catalog.public_branch_slug' => 'rusape-seo',
        ]);

        $platform = Platform::create(['name' => 'TMS', 'slug' => 'tms-seo', 'currency_code' => 'USD']);
        $this->branch = Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Rusape Branch',
            'code' => 'RUS-SEO',
            'slug' => 'rusape-seo',
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);
        $unit = Unit::create([
            'branch_id' => $this->branch->id,
            'code' => 'EA-SEO',
            'name' => 'Each',
            'allow_decimal' => false,
            'is_active' => true,
        ]);
        $this->category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Filters',
            'slug' => 'filters',
            'is_active' => true,
        ]);
        $admin = User::factory()->platformAdmin()->create();
        $media = CmsMedia::create([
            'original_name' => 'air-filter.png',
            'original_path' => 'cms/air-filter.png',
            'mime_type' => 'image/png',
            'file_size' => 2048,
            'width' => 960,
            'height' => 640,
            'variants' => [
                '960_webp' => ['path' => 'cms/960-air-filter.webp', 'width' => 960, 'height' => 640, 'mime' => 'image/webp'],
                '960_avif' => ['path' => 'cms/960-air-filter.avif', 'width' => 960, 'height' => 640, 'mime' => 'image/avif'],
            ],
            'uploaded_by' => $admin->id,
        ]);
        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'base_unit_id' => $unit->id,
            'sku' => 'AIR-SEO',
            'name' => 'Air Filter',
            'description' => 'PRIVATE INTERNAL SUPPLIER NOTES MUST NOT BE PUBLIC',
            'selling_price_minor' => 1250,
            'currency_code' => 'USD',
            'track_stock' => true,
            'is_active' => true,
            'public_slug' => 'air-filter',
            'public_description' => 'A dependable air filter for daily driving.',
            'public_image_id' => $media->id,
            'public_image_alt' => 'Air filter resting on a workbench',
            'is_public' => true,
        ]);
        StockLevel::create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => '5',
            'quantity_reserved' => '0',
            'reorder_level' => '0',
        ]);

        $this->cmsPage = CmsPage::create([
            'title' => 'About Taziva',
            'slug' => 'about-taziva',
            'current_version' => 1,
            'published_version' => 1,
            'published_slug' => 'about-taziva',
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);
        CmsPageVersion::create([
            'cms_page_id' => $this->cmsPage->id,
            'version' => 1,
            'title' => 'About Taziva',
            'slug' => 'about-taziva',
            'sections' => [
                ['type' => 'text', 'heading' => 'Our story', 'body' => 'We help local drivers find the right motor spares.'],
                ['type' => 'image', 'media_id' => $media->id, 'alt' => 'A motor spares counter in Rusape'],
            ],
            'media_ids' => [$media->id],
            'created_by' => $admin->id,
        ]);
    }

    /** @return array{string, string, string, string, string} */
    private function indexablePaths(): array
    {
        return ['/', '/shop', '/shop/category/filters', '/shop/air-filter', '/about-taziva'];
    }

    private function parse(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function nodes(DOMDocument $dom, string $query): DOMNodeList
    {
        return (new DOMXPath($dom))->query($query);
    }

    private function value(DOMDocument $dom, string $query): ?string
    {
        $node = $this->nodes($dom, $query)->item(0);

        return $node?->nodeValue;
    }

    private function attribute(DOMDocument $dom, string $query, string $attribute): ?string
    {
        $node = $this->nodes($dom, $query)->item(0);

        return $node instanceof DOMElement ? $node->getAttribute($attribute) : null;
    }

    public function test_indexable_public_pages_have_unique_titles_and_concise_descriptions(): void
    {
        $titles = [];
        $descriptions = [];

        foreach ($this->indexablePaths() as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            $title = trim((string) $this->value($dom, '//head/title'));
            $description = trim((string) $this->value($dom, "//head/meta[@name='description']/@content"));

            $this->assertNotSame('', $title, "Missing title on {$path}");
            $this->assertNotSame('', $description, "Missing description on {$path}");
            $this->assertLessThanOrEqual(160, mb_strlen($description), "Description over 160 characters on {$path}");
            $titles[] = $title;
            $descriptions[] = $description;
        }

        $this->assertCount(count($titles), array_unique($titles));
        $this->assertCount(count($descriptions), array_unique($descriptions));
    }

    public function test_indexable_public_pages_have_self_referencing_canonicals(): void
    {
        foreach ($this->indexablePaths() as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            $canonical = $this->attribute($dom, "//head/link[@rel='canonical']", 'href');

            $this->assertNotNull($canonical, "Missing canonical on {$path}");
            $this->assertSame(self::SITE_URL.$path, $canonical);
        }
    }

    public function test_robots_meta_policy_distinguishes_indexable_and_private_pages(): void
    {
        foreach ($this->indexablePaths() as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            $this->assertSame('index, follow', $this->attribute($dom, "//head/meta[@name='robots']", 'content'), "Wrong robots meta on {$path}");
        }

        foreach (['/shop?q=filter', '/shop/air-filter/reserve'] as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            $this->assertStringContainsString('noindex', (string) $this->attribute($dom, "//head/meta[@name='robots']", 'content'));
        }

        $result = app(ReservationService::class)->create($this->branch, $this->product, [
            'quantity' => '1', 'name' => 'SEO Check', 'phone' => '263770000000',
        ], (string) Str::uuid());
        $dom = $this->parse($this->get(route('reservation.status', $result['token']))->assertOk()->getContent());
        $this->assertSame('noindex, nofollow', $this->attribute($dom, "//head/meta[@name='robots']", 'content'));

        $spa = file_get_contents(resource_path('../../frontend/index.html'));
        $this->assertStringNotContainsString('<meta name="robots" content="noindex, nofollow" />', $spa);
        $this->assertStringContainsString('<title>Taziva Motor Spares |', $spa);
        $spaHome = file_get_contents(resource_path('../../frontend/public/TMS_Homepage.html'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $spaHome);
    }

    public function test_sitemap_lists_only_indexable_public_urls(): void
    {
        $body = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $dom = $this->parse($body);
        $locations = [];
        foreach ($this->nodes($dom, '//url/loc') as $node) {
            $locations[] = $node->nodeValue;
        }

        foreach ($this->indexablePaths() as $path) {
            $this->assertContains(self::SITE_URL.$path, $locations);
        }
        foreach (['/app', '/platform', '/api', '/reservation', '/shop?q=', '/shop/air-filter/reserve'] as $privatePath) {
            $this->assertStringNotContainsString($privatePath, $body);
        }
        $this->assertCount(count(array_unique($locations)), $locations);
    }

    public function test_robots_route_is_environment_aware_and_not_shadowed_by_a_static_file(): void
    {
        config(['app.env' => 'testing']);
        $this->get('/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("User-agent: *\nDisallow: /", false);

        config(['app.env' => 'production']);
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Allow: /', false)
            ->assertSee('Disallow: /app', false)
            ->assertSee('Disallow: /platform', false)
            ->assertSee('Disallow: /login', false)
            ->assertSee('Disallow: /api', false)
            ->assertSee('Sitemap: '.self::SITE_URL.'/sitemap.xml', false);

        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_public_pages_have_one_h1_and_logical_heading_order(): void
    {
        foreach ($this->indexablePaths() as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            $h1 = $this->nodes($dom, '//body//h1');
            $this->assertSame(1, $h1->length, "Expected one H1 on {$path}");

            $firstHeading = $this->nodes($dom, '//body//h1|//body//h2|//body//h3')->item(0);
            $this->assertNotNull($firstHeading, "Missing headings on {$path}");
            $this->assertSame('h1', $firstHeading->nodeName, "First heading is not H1 on {$path}");
        }
    }

    public function test_public_images_have_descriptive_alt_text(): void
    {
        foreach (['/', '/shop', '/shop/category/filters', '/shop/air-filter', '/about-taziva'] as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            foreach ($this->nodes($dom, '//body//img') as $image) {
                $this->assertNotSame('', trim($image->getAttribute('alt')), "Empty alt on {$path}");
            }
        }

        $this->assertStringContainsString('alt="Air filter resting on a workbench"', $this->get('/shop/air-filter')->getContent());
        $this->assertStringContainsString('alt="A motor spares counter in Rusape"', $this->get('/about-taziva')->getContent());
    }

    public function test_json_ld_is_valid_and_matches_rendered_content(): void
    {
        $expected = [
            '/' => 'AutoPartsStore',
            '/shop' => 'ItemList',
            '/shop/category/filters' => 'ItemList',
            '/shop/air-filter' => 'Product',
            '/about-taziva' => 'WebPage',
        ];

        foreach ($expected as $path => $type) {
            $html = $this->get($path)->assertOk()->getContent();
            $dom = $this->parse($html);
            $script = $this->value($dom, "//head/script[@type='application/ld+json']");
            $data = json_decode((string) $script, true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($data, "Invalid JSON-LD on {$path}");
            $canonical = $this->attribute($dom, "//head/link[@rel='canonical']", 'href');

            $this->assertSame('https://schema.org', $data['@context'] ?? null, "Wrong JSON-LD context on {$path}: ".(string) $script);
            $this->assertSame($type, $data['@type']);
            $this->assertSame($canonical, $data['url']);
            $this->assertStringNotContainsString('PRIVATE INTERNAL SUPPLIER NOTES', $script);
        }

        $productDom = $this->parse($this->get('/shop/air-filter')->getContent());
        $productData = json_decode((string) $this->value($productDom, "//head/script[@type='application/ld+json']"), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('12.50', $productData['offers']['price']);
        $this->assertSame('USD', $productData['offers']['priceCurrency']);
        $this->assertStringContainsString('12.50', $productDom->getElementsByTagName('body')->item(0)->textContent);
    }

    public function test_og_image_dimensions_metadata_matches_the_asset(): void
    {
        $svg = (string) file_get_contents(public_path('images/torqueerp-social.svg'));
        preg_match('/width="(\d+)" height="(\d+)"/', $svg, $matches);

        $this->assertSame('1200', $matches[1] ?? null);
        $this->assertSame('630', $matches[2] ?? null);

        foreach ($this->indexablePaths() as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            $this->assertNotNull($this->attribute($dom, "//head/meta[@property='og:image']", 'content'));
            $this->assertSame('1200', $this->attribute($dom, "//head/meta[@property='og:image:width']", 'content'));
            $this->assertSame('630', $this->attribute($dom, "//head/meta[@property='og:image:height']", 'content'));
        }
    }

    public function test_favicon_and_language_are_present_on_every_public_page(): void
    {
        foreach (array_merge($this->indexablePaths(), ['/shop?q=filter', '/shop/air-filter/reserve']) as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            $this->assertSame('en-ZW', $this->attribute($dom, '//html', 'lang'), "Wrong html lang on {$path}");
            $favicon = $this->attribute($dom, "//head/link[@rel='icon']", 'href');
            $this->assertNotNull($favicon, "Missing favicon on {$path}");
            $this->assertStringEndsWith('/tms_logo_transparent.png', $favicon);
        }
        $this->assertFileExists(public_path('tms_logo_transparent.png'));
        $this->assertFileExists(resource_path('../../frontend/public/tms_logo_transparent.png'));
    }

    public function test_slugs_are_clean_and_internal_links_stay_on_public_routes(): void
    {
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->product->public_slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->category->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->cmsPage->slug);

        foreach ($this->indexablePaths() as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            foreach ($this->nodes($dom, '//body//a/@href') as $href) {
                $link = $href->nodeValue;
                if (str_starts_with($link, '#') || str_starts_with($link, 'http')) {
                    continue;
                }
                $this->assertStringNotContainsString('/app', $link, "Private link on {$path}");
                $this->assertStringNotContainsString('/platform', $link, "Private link on {$path}");
                $this->assertStringNotContainsString('/api/', $link, "API link on {$path}");
                $this->assertStringNotContainsString('javascript:', $link);
            }
        }
    }

    public function test_private_workspace_urls_and_data_are_not_exposed_in_public_html(): void
    {
        foreach ($this->indexablePaths() as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('/app', $html, "Private workspace link on {$path}");
            $this->assertStringNotContainsString('/platform', $html, "Private workspace link on {$path}");
            $this->assertStringNotContainsString('PRIVATE INTERNAL SUPPLIER NOTES', $html);
        }

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/app', false)->assertDontSee('/platform', false);
        $this->get('/draft-page')->assertNotFound();
    }

    public function test_unpublished_pages_private_media_and_other_branch_data_are_not_public(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $draft = CmsPage::create([
            'title' => 'Draft Only',
            'slug' => 'draft-only',
            'current_version' => 1,
            'created_by' => $admin->id,
        ]);
        CmsPageVersion::create([
            'cms_page_id' => $draft->id,
            'version' => 1,
            'title' => 'Draft Only',
            'slug' => 'draft-only',
            'sections' => [['type' => 'text', 'heading' => 'Draft', 'body' => 'Unpublished body.']],
            'media_ids' => [],
            'created_by' => $admin->id,
        ]);
        $unreferencedMedia = CmsMedia::create([
            'original_name' => 'private.png',
            'original_path' => 'cms/private.png',
            'mime_type' => 'image/png',
            'file_size' => 1024,
            'width' => 10,
            'height' => 10,
            'variants' => [],
            'uploaded_by' => $admin->id,
        ]);

        $otherPlatform = Platform::create(['name' => 'Other', 'slug' => 'other-seo', 'currency_code' => 'USD']);
        $otherBranch = Branch::create([
            'platform_id' => $otherPlatform->id,
            'name' => 'Other Branch',
            'code' => 'OTH-SEO',
            'slug' => 'other-branch-seo',
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);
        $otherUnit = Unit::create([
            'branch_id' => $otherBranch->id,
            'code' => 'EA-OTHER',
            'name' => 'Each',
            'allow_decimal' => false,
            'is_active' => true,
        ]);
        Product::create([
            'branch_id' => $otherBranch->id,
            'base_unit_id' => $otherUnit->id,
            'sku' => 'OTHER-SEO',
            'name' => 'Other Branch Part',
            'description' => 'Other branch private notes.',
            'selling_price_minor' => 1000,
            'currency_code' => 'USD',
            'is_active' => true,
            'public_slug' => 'other-branch-part',
            'public_description' => 'Not for the configured public branch.',
            'is_public' => true,
        ]);

        $this->get('/draft-only')->assertNotFound();
        $this->get('/api/media/'.$unreferencedMedia->id.'/original')->assertNotFound();
        $this->get('/shop/other-branch-part')->assertNotFound();
        $this->get('/shop')->assertOk()->assertDontSee('Other Branch Part');
        $this->get('/sitemap.xml')->assertOk()
            ->assertDontSee('draft-only', false)
            ->assertDontSee('other-branch-part', false);
    }

    public function test_rendered_public_html_is_structurally_valid(): void
    {
        foreach ($this->indexablePaths() as $path) {
            $dom = $this->parse($this->get($path)->assertOk()->getContent());
            $this->assertSame(1, $dom->getElementsByTagName('html')->length, "Invalid html element count on {$path}");
            $this->assertSame(1, $dom->getElementsByTagName('head')->length, "Invalid head element count on {$path}");
            $this->assertSame(1, $dom->getElementsByTagName('body')->length, "Invalid body element count on {$path}");
            $this->assertSame(1, $dom->getElementsByTagName('main')->length, "Invalid main element count on {$path}");

            $ids = [];
            foreach ($this->nodes($dom, '//*[@id]') as $element) {
                $ids[] = $element->getAttribute('id');
            }
            $this->assertCount(count(array_unique($ids)), $ids, "Duplicate ids on {$path}");
        }
    }
}
