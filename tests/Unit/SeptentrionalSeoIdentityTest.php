<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Public\SeoPreviewController;
use Illuminate\Foundation\Testing\TestCase;

class SeptentrionalSeoIdentityTest extends TestCase
{
    private function preview(?string $image): string
    {
        $method = new \ReflectionMethod(SeoPreviewController::class, 'preview');
        return $method->invoke(new SeoPreviewController, [
            'title' => 'Actualités Septentrional', 'description' => 'Informations du Togo',
            'image' => $image, 'type' => 'website', 'schemaType' => 'WebPage',
            'canonicalUrl' => 'https://www.septentrional.tg/pages/test',
            'publishedTime' => null, 'modifiedTime' => null,
        ])->getContent();
    }

    public function test_a_page_without_an_image_does_not_advertise_the_old_brand_asset(): void
    {
        $html = $this->preview(null);
        $this->assertStringContainsString('Septentrional', $html);
        $this->assertStringNotContainsString('property="og:image"', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('content="summary"', $html);
    }

    public function test_editorial_images_remain_in_social_previews(): void
    {
        $html = $this->preview('https://example.org/photo.jpg');
        $this->assertStringContainsString('property="og:image" content="https://example.org/photo.jpg"', $html);
        $this->assertStringContainsString('<img src="https://example.org/photo.jpg"', $html);
        $this->assertStringContainsString('content="summary_large_image"', $html);
    }
}
