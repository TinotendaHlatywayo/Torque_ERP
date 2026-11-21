<?php

namespace App\Support;

use App\Models\CmsMedia;
use App\Models\CmsSiteContent;
use Illuminate\Support\Facades\Schema;

class SiteContent
{
    public function content(): array
    {
        if (! Schema::hasTable('cms_site_contents')) {
            return CmsSiteContent::defaults();
        }

        $site = CmsSiteContent::query()->where('key', 'default')->first();

        return $site ? $site->publicContent() : CmsSiteContent::defaults();
    }

    public function images(?array $content = null): array
    {
        $content ??= $this->content();
        $defaults = CmsSiteContent::imageDefaults();
        $images = [];

        foreach (CmsSiteContent::mediaKeys() as $key) {
            $media = isset($content[$key]) && $content[$key] ? CmsMedia::query()->find($content[$key]) : null;
            $fallback = $defaults[$key];
            $images[$key] = [
                'url' => $media ? $this->imageUrl($media) : url($fallback['url']),
                'alt' => $content[$this->imageAltKey($key)] ?: $fallback['alt'],
            ];
        }

        return $images;
    }

    public function imageAltKey(string $mediaKey): string
    {
        return match ($mediaKey) {
            'favicon_media_id', 'logo_media_id' => 'logo_alt',
            'home_hero_media_id' => 'home_hero_image_alt',
            'about_media_id' => 'about_image_alt',
            'services_media_id' => 'services_image_alt',
            'contact_media_id' => 'contact_image_alt',
            default => 'logo_alt',
        };
    }

    private function imageUrl(CmsMedia $media): string
    {
        $variants = $media->variants ?? [];
        $preferred = $media->preferred_variant;
        $variant = $preferred && isset($variants[$preferred])
            ? $preferred
            : (isset($variants['960_webp']) ? '960_webp' : (isset($variants['960_avif']) ? '960_avif' : 'original'));

        return route('public.media', [$media->id, str_replace('_', '-', $variant)]);
    }
}
