<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsPageVersion;
use App\Models\CmsSiteContent;
use App\Models\Product;
use App\Support\CmsImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class CmsContentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CmsPage::query()->orderBy('title')->get()->map(fn (CmsPage $page) => [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->published_version ? 'published' : 'draft',
            'current_version' => $page->current_version,
            'published_version' => $page->published_version,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ])]);
    }

    public function show(CmsPage $cmsPage): JsonResponse
    {
        return response()->json(['data' => $this->pageData($cmsPage)]);
    }

    public function versions(CmsPage $cmsPage): JsonResponse
    {
        return response()->json(['data' => $cmsPage->versions()->get()->map(fn (CmsPageVersion $version) => [
            'id' => $version->id,
            'version' => $version->version,
            'title' => $version->title,
            'slug' => $version->slug,
            'is_published' => $version->version === $cmsPage->published_version,
            'created_at' => $version->created_at?->toIso8601String(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPage($request);
        $page = DB::transaction(function () use ($data, $request) {
            $page = CmsPage::create([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'current_version' => 1,
                'created_by' => $request->user()->id,
            ]);
            $this->snapshot($page, 1, $data, $request);

            return $page;
        });

        return response()->json(['data' => $this->pageData($page)], 201);
    }

    public function update(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $data = $this->validatedPage($request, $cmsPage);
        DB::transaction(function () use ($cmsPage, $data, $request) {
            $page = CmsPage::query()->lockForUpdate()->findOrFail($cmsPage->id);
            $version = $page->current_version + 1;
            $page->update(['title' => $data['title'], 'slug' => $data['slug'], 'current_version' => $version]);
            $this->snapshot($page, $version, $data, $request);
        });

        return response()->json(['data' => $this->pageData($cmsPage->fresh())]);
    }

    public function publish(CmsPage $cmsPage): JsonResponse
    {
        $page = DB::transaction(function () use ($cmsPage) {
            $page = CmsPage::query()->lockForUpdate()->findOrFail($cmsPage->id);
            abort_if($page->current_version < 1, 422, 'Save a draft before publishing.');
            $version = $page->versions()->where('version', $page->current_version)->firstOrFail();
            validator(['slug' => $version->slug], ['slug' => [Rule::unique('cms_pages', 'published_slug')->ignore($page->id)]])->validate();
            $page->update(['published_version' => $page->current_version, 'published_slug' => $version->slug, 'published_at' => now()]);

            return $page;
        });

        return response()->json(['data' => $this->pageData($page->fresh())]);
    }

    public function rollback(CmsPage $cmsPage, CmsPageVersion $version, Request $request): JsonResponse
    {
        abort_unless($version->cms_page_id === $cmsPage->id, 404);
        validator(['slug' => $version->slug], [
            'slug' => [
                Rule::unique('cms_pages', 'slug')->ignore($cmsPage->id),
                Rule::unique('cms_pages', 'published_slug')->ignore($cmsPage->id),
            ],
        ])->validate();
        DB::transaction(function () use ($cmsPage, $version, $request) {
            $page = CmsPage::query()->lockForUpdate()->findOrFail($cmsPage->id);
            $nextVersion = $page->current_version + 1;
            $data = ['title' => $version->title, 'slug' => $version->slug, 'sections' => $version->sections];
            $page->update(['title' => $data['title'], 'slug' => $data['slug'], 'current_version' => $nextVersion]);
            $this->snapshot($page, $nextVersion, $data, $request);
        });

        return response()->json(['data' => $this->pageData($cmsPage->fresh())]);
    }

    public function upload(Request $request, CmsImageProcessor $processor): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        try {
            $media = $processor->store($data['image'], $request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->mediaData($media)], 201);
    }

    public function mediaIndex(): JsonResponse
    {
        return response()->json(['data' => CmsMedia::query()->latest()->limit(100)->get()->map(fn (CmsMedia $media) => $this->mediaData($media))]);
    }

    public function mediaPreview(CmsMedia $cmsMedia, string $variant)
    {
        $variant = str_replace('-', '_', $variant);
        $path = $cmsMedia->variants[$variant]['path'] ?? ($variant === 'original' ? $cmsMedia->original_path : null);
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $mime = $cmsMedia->variants[$variant]['mime'] ?? $cmsMedia->mime_type;

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function publicMedia(CmsMedia $cmsMedia, string $variant)
    {
        $isPublishedPageMedia = CmsPageVersion::query()
            ->whereJsonContains('media_ids', $cmsMedia->id)
            ->whereHas('page', fn ($query) => $query->whereNotNull('published_version')
                ->whereColumn('cms_pages.published_version', 'cms_page_versions.version'))
            ->exists();
        $isPublishedProductMedia = Product::query()->where('public_image_id', $cmsMedia->id)
            ->where('is_public', true)->where('is_active', true)
            ->whereHas('branch', fn ($query) => $query->where('slug', config('catalog.public_branch_slug'))
                ->where('status', Branch::STATUS_ACTIVE))->exists();
        $isPublishedSiteMedia = CmsSiteContent::query()->where('key', 'default')
            ->whereNotNull('published_content')
            ->where(function ($query) use ($cmsMedia): void {
                foreach (CmsSiteContent::mediaKeys() as $key) {
                    $query->orWhereJsonContains('published_content->'.$key, $cmsMedia->id);
                }
            })->exists();
        abort_unless($isPublishedPageMedia || $isPublishedProductMedia || $isPublishedSiteMedia, 404);

        $variant = str_replace('-', '_', $variant);
        $path = $cmsMedia->variants[$variant]['path'] ?? ($variant === 'original' ? $cmsMedia->original_path : null);
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $mime = $cmsMedia->variants[$variant]['mime'] ?? $cmsMedia->mime_type;

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function validatedPage(Request $request, ?CmsPage $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'not_in:robots.txt,sitemap.xml,login,api,app,platform,media,shop', Rule::unique('cms_pages', 'slug')->ignore($page?->id), Rule::unique('cms_pages', 'published_slug')->ignore($page?->id)],
            'sections' => ['required', 'array', 'min:1', 'max:20'],
            'sections.*.type' => ['required', 'string', 'in:hero,text,image'],
            'sections.*.heading' => ['nullable', 'string', 'max:180'],
            'sections.*.body' => ['nullable', 'string', 'max:5000'],
            'sections.*.media_id' => ['nullable', 'string', 'exists:cms_media,id'],
            'sections.*.alt' => ['nullable', 'string', 'max:250'],
        ]);

        foreach ($data['sections'] as $index => $section) {
            if ($section['type'] === 'image' && empty($section['media_id'])) {
                abort(422, 'Every image section must select an uploaded image.');
            }
            if ($section['type'] === 'image' && empty(trim($section['alt'] ?? ''))) {
                abort(422, 'Every published image needs descriptive alt text.');
            }
            if ($section['type'] !== 'image' && ! empty($section['media_id'])) {
                abort(422, 'Only image sections can reference media.');
            }
            $data['sections'][$index]['heading'] = trim($section['heading'] ?? '');
            $data['sections'][$index]['body'] = trim($section['body'] ?? '');
            $data['sections'][$index]['alt'] = trim($section['alt'] ?? '');
        }

        return $data;
    }

    private function snapshot(CmsPage $page, int $version, array $data, Request $request): void
    {
        $mediaIds = collect($data['sections'])->pluck('media_id')->filter()->unique()->values()->all();
        CmsPageVersion::create([
            'cms_page_id' => $page->id,
            'version' => $version,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'sections' => $data['sections'],
            'media_ids' => $mediaIds,
            'created_by' => $request->user()->id,
        ]);
    }

    private function pageData(CmsPage $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->published_version ? 'published' : 'draft',
            'current_version' => $page->current_version,
            'published_version' => $page->published_version,
            'sections' => $page->versions()->where('version', $page->current_version)->first()?->sections ?? [],
        ];
    }

    private function mediaData(CmsMedia $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
            'preferred_variant' => $media->preferred_variant,
            'variants' => collect($media->variants)->map(fn ($variant, $key) => [
                'key' => $key,
                'url' => route('platform.cms.media.preview', [$media, str_replace('_', '-', $key)]),
                'width' => $variant['width'],
                'height' => $variant['height'],
                'mime_type' => $variant['mime'],
                'bytes' => $variant['bytes'] ?? null,
                'is_preferred' => $key === $media->preferred_variant,
            ])->values(),
        ];
    }
}
