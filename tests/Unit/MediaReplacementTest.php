<?php

namespace Tests\Unit;

use App\Support\MediaReplacement;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MediaReplacementTest extends TestCase
{
    public function test_it_deletes_old_disk_media_only_after_a_successful_operation(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/old.jpg', 'old');
        Storage::disk('public')->put('media/new.jpg', 'new');

        $result = MediaReplacement::run(function (MediaReplacement $media): string {
            $media->replaceOnDisk('media/new.jpg', 'media/old.jpg');

            Storage::disk('public')->assertExists('media/old.jpg');

            return 'persisted';
        });

        $this->assertSame('persisted', $result);
        Storage::disk('public')->assertExists('media/new.jpg');
        Storage::disk('public')->assertMissing('media/old.jpg');
    }

    public function test_it_deletes_all_new_disk_media_and_preserves_old_media_on_failure(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/old-a.jpg', 'old-a');
        Storage::disk('public')->put('media/old-b.mp3', 'old-b');
        Storage::disk('public')->put('media/new-a.jpg', 'new-a');
        Storage::disk('public')->put('media/new-b.mp3', 'new-b');

        try {
            MediaReplacement::run(function (MediaReplacement $media): void {
                $media->replaceOnDisk('media/new-a.jpg', 'media/old-a.jpg');
                $media->replaceOnDisk('media/new-b.mp3', 'media/old-b.mp3');

                throw new RuntimeException('Échec simulé de la sauvegarde DB.');
            });

            $this->fail('L’exception attendue n’a pas été levée.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Échec simulé de la sauvegarde DB.', $exception->getMessage());
        }

        Storage::disk('public')->assertMissing('media/new-a.jpg');
        Storage::disk('public')->assertMissing('media/new-b.mp3');
        Storage::disk('public')->assertExists('media/old-a.jpg');
        Storage::disk('public')->assertExists('media/old-b.mp3');
    }

    public function test_it_applies_the_same_lifecycle_to_local_public_files(): void
    {
        $directory = sys_get_temp_dir().'/septentrional-media-replacement-'.bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        $oldPath = $directory.'/old.mp4';
        $newPath = $directory.'/new.mp4';
        file_put_contents($oldPath, 'old');
        file_put_contents($newPath, 'new');

        try {
            MediaReplacement::run(function (MediaReplacement $media) use ($newPath, $oldPath): void {
                $media->replaceLocalFile($newPath, $oldPath);
            });

            $this->assertFileExists($newPath);
            $this->assertFileDoesNotExist($oldPath);
        } finally {
            if (is_file($newPath)) {
                unlink($newPath);
            }

            if (is_file($oldPath)) {
                unlink($oldPath);
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
