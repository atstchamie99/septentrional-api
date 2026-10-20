<?php

namespace Tests\Unit;

use App\Services\OptimizedImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OptimizedImageStorageTest extends TestCase
{
    public function test_it_resizes_and_converts_uploaded_images_to_webp(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('hero.jpg', 2400, 1600);

        $path = app(OptimizedImageStorage::class)->store(
            $image,
            'tests',
            maxWidth: 1200,
            maxHeight: 1200,
        );

        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.webp', $path);

        [$width, $height] = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame(1200, $width);
        $this->assertSame(800, $height);
    }
}
