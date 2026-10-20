<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\OccasionSpeciale;
use App\Models\Partenaire;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class MigrateLegacyPublicImages extends Command
{
    protected $signature = 'media:migrate-legacy-images {--dry-run : List files without copying them}';

    protected $description = 'Copies referenced legacy images into the restricted public media directory';

    /** @var array<class-string<Model>, string> */
    private const SOURCES = [
        Article::class => 'image_principale',
        Video::class => 'miniature',
        OccasionSpeciale::class => 'image',
        Partenaire::class => 'logo',
    ];

    public function handle(): int
    {
        $legacyRoot = realpath(storage_path('app/public'));
        $mediaRoot = realpath(storage_path('app/public/media'));

        if ($legacyRoot === false || $mediaRoot === false) {
            $this->error('The legacy or public-media storage directory is unavailable.');

            return self::FAILURE;
        }

        $copied = 0;
        $skipped = 0;

        foreach (self::SOURCES as $model => $column) {
            $model::query()
                ->whereNotNull($column)
                ->select(['id', $column])
                ->orderBy('id')
                ->chunkById(200, function ($records) use ($column, $legacyRoot, $mediaRoot, &$copied, &$skipped): void {
                    foreach ($records as $record) {
                        $path = ltrim((string) $record->{$column}, '/');

                        if (! $this->isPublicImagePath($path)) {
                            $skipped++;
                            continue;
                        }

                        $source = $legacyRoot . DIRECTORY_SEPARATOR . $path;
                        $destination = $mediaRoot . DIRECTORY_SEPARATOR . $path;

                        if (! is_file($source)) {
                            if ($this->output->isVerbose()) {
                                $this->warn("Missing legacy image: {$path}");
                            }
                            $skipped++;
                            continue;
                        }

                        if (is_file($destination)) {
                            $skipped++;
                            continue;
                        }

                        if ($this->option('dry-run')) {
                            $this->line("Would copy: {$path}");
                            $copied++;
                            continue;
                        }

                        File::ensureDirectoryExists(dirname($destination));
                        File::copy($source, $destination);
                        $copied++;
                    }
                });
        }

        $verb = $this->option('dry-run') ? 'would be copied' : 'copied';
        $this->info("{$copied} image(s) {$verb}; {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function isPublicImagePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..')) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), [
            'avif', 'gif', 'jpeg', 'jpg', 'png', 'webp',
        ], true);
    }
}
