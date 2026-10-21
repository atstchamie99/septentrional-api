<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CommuniquePdfStorage
{
    public function canonicalPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('Le chemin du communiqué est vide ou invalide.');
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Le chemin du communiqué est invalide.');
            }
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new RuntimeException('Le fichier du communiqué doit être un PDF.');
        }

        return implode('/', $segments);
    }

    public function movePublicToPrivate(string $path): void
    {
        $this->move($path, 'public', 'local');
    }

    public function restorePrivateToPublic(string $path): void
    {
        $this->move($path, 'local', 'public');
    }

    private function move(string $path, string $sourceDisk, string $targetDisk): void
    {
        $path = $this->canonicalPath($path);
        $source = Storage::disk($sourceDisk);
        $target = Storage::disk($targetDisk);

        if (! $source->exists($path)) {
            if ($target->exists($path)) {
                return;
            }

            throw new RuntimeException("Le PDF référencé est introuvable : {$path}");
        }

        $this->copy($source, $target, $path);

        if (! $source->delete($path)) {
            $target->delete($path);
            throw new RuntimeException("Impossible de supprimer le PDF source : {$path}");
        }
    }

    private function copy(Filesystem $source, Filesystem $target, string $path): void
    {
        $stream = $source->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException("Impossible de lire le PDF source : {$path}");
        }

        try {
            if (! $target->writeStream($path, $stream)) {
                throw new RuntimeException("Impossible de copier le PDF : {$path}");
            }
        } finally {
            fclose($stream);
        }
    }
}
