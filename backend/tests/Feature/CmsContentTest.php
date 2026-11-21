<?php

namespace Tests\Feature;

use App\Models\CmsMedia;
use App\Models\CmsPageVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->platformAdmin()->create();
    }

    private function asAdmin()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->admin->createToken('cms')->plainTextToken);
    }

    private function pagePayload(string $title = 'About Taziva', string $slug = 'about-taziva', string $body = 'Local motor spares for Rusape.')
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'sections' => [
                ['type' => 'hero', 'heading' => $title, 'body' => 'Your local motor spares partner.'],
                ['type' => 'text', 'heading' => 'Our service', 'body' => $body],
            ],
        ];
    }

    public function test_publication_keeps_the_previous_version_live_until_the_new_draft_is_published(): void
    {
        $created = $this->asAdmin()->postJson('/api/platform/cms/pages', $this->pagePayload())->assertCreated();
        $pageId = $created->json('data.id');

        $this->get('/about-taziva')->assertNotFound();
        $this->asAdmin()->postJson("/api/platform/cms/pages/{$pageId}/publish")->assertOk();
        $this->get('/about-taziva')->assertOk()->assertSee('Local motor spares for Rusape.');

        $updated = $this->pagePayload('About Taziva', 'our-story', 'This is a reviewed draft.');
        $this->asAdmin()->putJson("/api/platform/cms/pages/{$pageId}", $updated)->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_version', 1);

        $this->get('/about-taziva')->assertOk()->assertSee('Local motor spares for Rusape.');
        $this->get('/our-story')->assertNotFound();

        $this->asAdmin()->postJson("/api/platform/cms/pages/{$pageId}/publish")->assertOk()
            ->assertJsonPath('data.published_version', 2);
        $this->get('/our-story')->assertOk()->assertSee('This is a reviewed draft.');
        $this->assertStringContainsString('/our-story', $this->get('/sitemap.xml')->assertOk()->getContent());
    }

    public function test_rollback_creates_a_new_draft_and_keeps_the_live_version_until_republished(): void
    {
        $pageId = $this->asAdmin()->postJson('/api/platform/cms/pages', $this->pagePayload())->assertCreated()->json('data.id');
        $this->asAdmin()->postJson("/api/platform/cms/pages/{$pageId}/publish")->assertOk();
        $this->asAdmin()->putJson("/api/platform/cms/pages/{$pageId}", $this->pagePayload('About Taziva', 'about-taziva', 'Second revision.'))->assertOk();
        $versionOne = CmsPageVersion::where('cms_page_id', $pageId)->where('version', 1)->firstOrFail();

        $this->asAdmin()->postJson("/api/platform/cms/pages/{$pageId}/rollback/{$versionOne->id}")
            ->assertOk()->assertJsonPath('data.current_version', 3)->assertJsonPath('data.published_version', 1);
        $this->get('/about-taziva')->assertOk()->assertSee('Local motor spares for Rusape.');
    }

    public function test_cms_requires_platform_admin_permission(): void
    {
        $user = User::factory()->create();
        $this->withHeader('Authorization', 'Bearer '.$user->createToken('branch')->plainTextToken)
            ->getJson('/api/platform/cms/pages')
            ->assertForbidden();
    }

    public function test_uploaded_media_is_private_until_referenced_by_a_published_page(): void
    {
        $upload = $this->asAdmin()->postJson('/api/platform/cms/media', [
            'image' => UploadedFile::fake()->image('bearing.png', 120, 80),
        ])->assertCreated();
        $mediaId = $upload->json('data.id');
        $media = CmsMedia::findOrFail($mediaId);
        $stored = $media->variants['480_webp']['path'] ?? $media->original_path;

        $this->get("/api/media/{$mediaId}/original")->assertNotFound();
        $payload = [
            'title' => 'Parts image',
            'slug' => 'parts-image',
            'sections' => [
                ['type' => 'hero', 'heading' => 'Parts image', 'body' => 'A page with a photo.'],
                ['type' => 'image', 'media_id' => $mediaId, 'alt' => 'A motor part on a workbench'],
            ],
        ];
        $pageId = $this->asAdmin()->postJson('/api/platform/cms/pages', $payload)->assertCreated()->json('data.id');
        $this->asAdmin()->postJson("/api/platform/cms/pages/{$pageId}/publish")->assertOk();

        $response = $this->get("/api/media/{$mediaId}/original")->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertTrue(Storage::disk('local')->exists($stored));
    }

    public function test_upload_rejects_non_images(): void
    {
        $this->asAdmin()->postJson('/api/platform/cms/media', [
            'image' => UploadedFile::fake()->create('payload.exe', 2, 'application/octet-stream'),
        ])->assertUnprocessable();
    }

    public function test_upload_records_the_smallest_preferred_modern_variant(): void
    {
        $upload = $this->asAdmin()->postJson('/api/platform/cms/media', [
            'image' => UploadedFile::fake()->image('brake.png', 1200, 800),
        ])->assertCreated();

        $media = CmsMedia::findOrFail($upload->json('data.id'));
        $variants = $media->variants ?? [];

        $this->assertNotEmpty($variants);
        $this->assertContains($media->preferred_variant, array_keys($variants));
        $this->assertStringStartsWith('960_', (string) $media->preferred_variant);
        $displayVariants = collect($variants)->filter(fn (array $variant, string $key): bool => str_starts_with($key, '960_'));
        $this->assertLessThanOrEqual(
            $displayVariants->pluck('bytes')->filter()->min(),
            $variants[$media->preferred_variant]['bytes'],
        );
    }

    public function test_site_content_only_changes_public_website_after_publishing(): void
    {
        $upload = $this->asAdmin()->postJson('/api/platform/cms/media', [
            'image' => UploadedFile::fake()->image('site-logo.png', 400, 160),
        ])->assertCreated();
        $mediaId = $upload->json('data.id');

        $this->asAdmin()->getJson('/api/platform/cms/site-content')->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->asAdmin()->putJson('/api/platform/cms/site-content', [
            'content' => [
                'home_heading' => 'Managed homepage heading',
                'logo_media_id' => $mediaId,
            ],
        ])->assertOk()->assertJsonPath('data.content.home_heading', 'Managed homepage heading');

        $this->getJson('/api/public/site')->assertOk()
            ->assertJsonMissing(['Managed homepage heading']);

        $this->asAdmin()->postJson('/api/platform/cms/site-content/publish')->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->getJson('/api/public/site')->assertOk()
            ->assertJsonPath('content.text.home_heading', 'Managed homepage heading')
            ->assertJsonPath('content.images.logo_media_id.alt', 'Taziva Motor Spares');

        $media = CmsMedia::findOrFail($mediaId);
        $variant = str_replace('_', '-', (string) $media->preferred_variant);
        $this->get("/api/media/{$mediaId}/{$variant}")->assertOk();
    }
}
