<?php

namespace App\Services;

use App\Models\MediaLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OptimizedImageStorage
{
    public function store(
        UploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $maxWidth = 1920,
        int $maxHeight = 1920,
        int $quality = 82,
    ): string {
        if (
            ! function_exists('imagecreatefromstring')
            || ! function_exists('imagewebp')
            || $file->getMimeType() === 'image/gif'
        ) {
            return $this->storeOriginal($file, $directory, $disk);
        }

        try {
            $contents = file_get_contents($file->getRealPath());
            $source = $contents === false ? false : @imagecreatefromstring($contents);

            if ($source === false) {
                return $this->storeOriginal($file, $directory, $disk);
            }

            $source = $this->applyExifOrientation($source, $file);
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $scale = min(1, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));

            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);

            imagecopyresampled(
                $target,
                $source,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight,
            );

            ob_start();
            $encoded = imagewebp($target, null, max(1, min($quality, 100)));
            $optimizedContents = ob_get_clean();

            imagedestroy($source);
            imagedestroy($target);

            if (! $encoded || ! is_string($optimizedContents) || $optimizedContents === '') {
                return $this->storeOriginal($file, $directory, $disk);
            }

            $path = trim($directory, '/') . '/' . Str::uuid() . '.webp';

            if (! Storage::disk($disk)->put($path, $optimizedContents)) {
                return $this->storeOriginal($file, $directory, $disk);
            }

            $this->indexMedia($path, $file, $disk);
            return $path;
        } catch (Throwable) {
            return $this->storeOriginal($file, $directory, $disk);
        }
    }

    private function storeOriginal(UploadedFile $file, string $directory, string $disk): string
    {
        $path = $file->store($directory, $disk);
        $this->indexMedia($path, $file, $disk);

        return $path;
    }

    private function indexMedia(string $path, UploadedFile $file, string $disk): void
    {
        if ($disk !== 'public') {
            return;
        }

        MediaLibrary::firstOrCreate(
            ['source' => 'upload', 'path' => $path],
            [
                'uploaded_by' => auth()->id(),
                'type' => 'image',
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ],
        );
    }

    private function applyExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (
            $file->getMimeType() !== 'image/jpeg'
            || ! function_exists('exif_read_data')
        ) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => null,
        };

        if ($angle === null) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}
