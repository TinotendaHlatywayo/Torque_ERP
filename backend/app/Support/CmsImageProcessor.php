<?php

namespace App\Support;

use App\Models\CmsMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CmsImageProcessor
{
    public function store(UploadedFile $file, User $user): CmsMedia
    {
        $size = getimagesize($file->getRealPath());
        if ($size === false || $size[0] > 4000 || $size[1] > 4000) {
            throw new RuntimeException('Choose a valid image no larger than 4000 by 4000 pixels.');
        }

        $image = imagecreatefromstring(file_get_contents($file->getRealPath()));
        if ($image === false) {
            throw new RuntimeException('This image format cannot be processed on this server.');
        }

        $id = (string) Str::ulid();
        $directory = 'cms/'.$id;
        $disk = Storage::disk('local');
        $extension = strtolower($file->guessExtension() ?: 'img');
        $originalPath = $directory.'/original.'.$extension;
        $disk->putFileAs($directory, $file, basename($originalPath));

        try {
            $variants = [];
            foreach ([480, 960, 1600] as $maxWidth) {
                if (! function_exists('imagewebp') && ! function_exists('imageavif')) {
                    break;
                }

                $ratio = min(1, $maxWidth / $size[0]);
                $width = (int) round($size[0] * $ratio);
                $height = max(1, (int) round($size[1] * $ratio));
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);

                foreach (['webp', 'avif'] as $format) {
                    $encoder = $format === 'webp' ? 'imagewebp' : 'imageavif';
                    if (! function_exists($encoder)) {
                        continue;
                    }
                    ob_start();
                    $encoder($canvas, null, 82);
                    $bytes = ob_get_clean();
                    if (! is_string($bytes) || $bytes === '') {
                        continue;
                    }
                    $path = $directory.'/'.$maxWidth.'.'.$format;
                    $disk->put($path, $bytes);
                    $variants[$maxWidth.'_'.$format] = [
                        'path' => $path,
                        'mime' => 'image/'.$format,
                        'width' => $width,
                        'height' => $height,
                        'bytes' => strlen($bytes),
                    ];
                }
                imagedestroy($canvas);
            }

            return CmsMedia::create([
                'id' => $id,
                'preferred_variant' => $this->preferredVariant($variants),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'original_path' => $originalPath,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
                'width' => $size[0],
                'height' => $size[1],
                'variants' => $variants,
                'uploaded_by' => $user->id,
            ]);
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($directory);
            throw $exception;
        } finally {
            imagedestroy($image);
        }
    }

    private function preferredVariant(array $variants): ?string
    {
        $candidates = collect($variants)
            ->filter(fn (array $variant, string $key): bool => str_starts_with($key, '960_'))
            ->whenEmpty(fn () => collect($variants))
            ->sortBy('bytes');

        $key = $candidates->keys()->first();

        return is_string($key) ? $key : null;
    }
}
