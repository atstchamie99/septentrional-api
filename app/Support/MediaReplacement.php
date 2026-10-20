<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class MediaReplacement
{
    /**
     * @var array<int, array{disk: string, path: string}>
     */
    private array $newDiskFiles = [];

    /**
     * @var array<int, array{disk: string, path: string}>
     */
    private array $oldDiskFiles = [];

    /**
     * @var array<int, string>
     */
    private array $newLocalFiles = [];

    /**
     * @var array<int, string>
     */
    private array $oldLocalFiles = [];

    /**
     * Exécute un remplacement de médias comme une seule opération logique.
     *
     * Le callback doit stocker les nouveaux fichiers, les enregistrer avec
     * replaceOnDisk()/replaceLocalFile(), puis persister les données dans une
     * transaction DB. En cas d'exception, seuls les nouveaux fichiers sont
     * supprimés. Les anciens ne sont supprimés qu'après le succès du callback.
     */
    public static function run(callable $operation): mixed
    {
        $replacement = new self;

        try {
            $result = $operation($replacement);
        } catch (Throwable $exception) {
            $replacement->deleteNewFiles();

            throw $exception;
        }

        $replacement->deleteOldFiles();

        return $result;
    }

    public function replaceOnDisk(
        string $newPath,
        ?string $oldPath = null,
        string $disk = 'public',
    ): string {
        $newPath = $this->requirePath($newPath);
        $this->newDiskFiles[] = ['disk' => $disk, 'path' => $newPath];

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $newPath) {
            $this->oldDiskFiles[] = ['disk' => $disk, 'path' => $oldPath];
        }

        return $newPath;
    }

    public function replaceLocalFile(string $newPath, ?string $oldPath = null): string
    {
        $newPath = $this->requirePath($newPath);
        $this->newLocalFiles[] = $newPath;

        $this->deleteLocalFileAfterSuccess(
            $oldPath !== $newPath ? $oldPath : null,
        );

        return $newPath;
    }

    public function deleteOnDiskAfterSuccess(?string $path, string $disk = 'public'): void
    {
        if ($path !== null && $path !== '') {
            $this->oldDiskFiles[] = ['disk' => $disk, 'path' => $path];
        }
    }

    public function deleteLocalFileAfterSuccess(?string $path): void
    {
        if ($path !== null && $path !== '') {
            $this->oldLocalFiles[] = $path;
        }
    }

    private function requirePath(string $path): string
    {
        if (trim($path) === '') {
            throw new \RuntimeException('Le stockage du nouveau média a échoué.');
        }

        return $path;
    }

    private function deleteNewFiles(): void
    {
        foreach ($this->newDiskFiles as $file) {
            $this->deleteFromDisk($file['disk'], $file['path']);
        }

        foreach ($this->newLocalFiles as $path) {
            $this->deleteLocalFile($path);
        }
    }

    private function deleteOldFiles(): void
    {
        foreach ($this->oldDiskFiles as $file) {
            $this->deleteFromDisk($file['disk'], $file['path']);
        }

        foreach ($this->oldLocalFiles as $path) {
            $this->deleteLocalFile($path);
        }
    }

    private function deleteFromDisk(string $disk, string $path): void
    {
        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function deleteLocalFile(string $path): void
    {
        try {
            File::delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
