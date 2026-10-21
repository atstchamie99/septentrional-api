<?php

namespace Tests\Unit;

use App\Support\CommuniquePdfStorage;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CommuniquePdfStorageTest extends TestCase
{
    public function test_it_moves_a_public_pdf_to_private_storage(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('communiques/avis.pdf', 'contenu');

        app(CommuniquePdfStorage::class)
            ->movePublicToPrivate('/storage/communiques/avis.pdf');

        Storage::disk('public')->assertMissing('communiques/avis.pdf');
        Storage::disk('local')->assertExists('communiques/avis.pdf');
        $this->assertSame(
            'contenu',
            Storage::disk('local')->get('communiques/avis.pdf'),
        );
    }

    public function test_it_restores_a_private_pdf_to_public_storage(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('local')->put('uploads/2022/avis.pdf', 'contenu');

        app(CommuniquePdfStorage::class)
            ->restorePrivateToPublic('uploads/2022/avis.pdf');

        Storage::disk('local')->assertMissing('uploads/2022/avis.pdf');
        Storage::disk('public')->assertExists('uploads/2022/avis.pdf');
    }

    public function test_it_is_idempotent_when_the_target_already_contains_the_pdf(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('local')->put('communiques/avis.pdf', 'contenu');

        app(CommuniquePdfStorage::class)
            ->movePublicToPrivate('communiques/avis.pdf');

        Storage::disk('local')->assertExists('communiques/avis.pdf');
    }

    #[DataProvider('invalidPaths')]
    public function test_it_rejects_unsafe_or_non_pdf_paths(string $path): void
    {
        $this->expectException(RuntimeException::class);

        app(CommuniquePdfStorage::class)->canonicalPath($path);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPaths(): array
    {
        return [
            'empty' => [''],
            'parent traversal' => ['../../etc/passwd.pdf'],
            'embedded traversal' => ['uploads/../secret.pdf'],
            'double separator' => ['uploads//avis.pdf'],
            'not a PDF' => ['uploads/avis.jpg'],
        ];
    }
}
