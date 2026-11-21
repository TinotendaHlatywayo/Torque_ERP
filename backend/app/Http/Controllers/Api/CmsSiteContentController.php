<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsSiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsSiteContentController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->site())]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'array', 'max:200'],
        ]);

        $site = $this->site();
        $site->update([
            'content' => $this->normalizeContent($data['content']),
            'version' => $site->version + 1,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->payload($site->fresh())]);
    }

    public function publish(): JsonResponse
    {
        $site = $this->site();
        abort_if($site->version < 1, 422, 'Save site content before publishing.');

        $site->update([
            'published_content' => $site->draftContent(),
            'published_version' => $site->version,
            'published_at' => now(),
        ]);

        return response()->json(['data' => $this->payload($site->fresh())]);
    }

    private function site(): CmsSiteContent
    {
        return CmsSiteContent::query()->firstOrCreate(
            ['key' => 'default'],
            ['content' => CmsSiteContent::defaults(), 'version' => 0],
        );
    }

    private function payload(CmsSiteContent $site): array
    {
        return [
            'content' => $site->draftContent(),
            'published_content' => $site->publicContent(),
            'version' => $site->version,
            'published_version' => $site->published_version,
            'status' => $site->published_version ? 'published' : 'draft',
            'published_at' => $site->published_at?->toIso8601String(),
            'updated_at' => $site->updated_at?->toIso8601String(),
        ];
    }

    private function normalizeContent(array $content): array
    {
        $defaults = CmsSiteContent::defaults();
        $mediaKeys = CmsSiteContent::mediaKeys();
        $normalized = $defaults;

        foreach ($content as $key => $value) {
            if (! array_key_exists($key, $defaults)) {
                continue;
            }

            if (in_array($key, $mediaKeys, true)) {
                validator(['value' => $value], ['value' => ['nullable', 'string', 'exists:cms_media,id']])->validate();
                $normalized[$key] = $value ?: null;

                continue;
            }

            abort_unless(is_string($value) || is_numeric($value) || $value === null, 422, 'Invalid site content value.');
            $text = trim((string) ($value ?? ''));
            abort_if(mb_strlen($text) > 5000, 422, 'Site content values must be 5000 characters or fewer.');
            $normalized[$key] = $text;
        }

        return $normalized;
    }
}
