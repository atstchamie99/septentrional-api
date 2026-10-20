<?php

use App\Support\CommuniquePdfStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $storage = app(CommuniquePdfStorage::class);
        $this->validateReferencedPaths($storage);

        $this->eachReferencedPath(
            fn (string $path) => $storage->movePublicToPrivate($path)
        );
    }

    public function down(): void
    {
        $storage = app(CommuniquePdfStorage::class);
        $this->validateReferencedPaths($storage);

        $this->eachReferencedPath(
            fn (string $path) => $storage->restorePrivateToPublic($path)
        );
    }

    /**
     * Valide tous les chemins avant le premier déplacement afin qu'un chemin
     * malformé ne puisse pas provoquer une migration partielle.
     */
    private function validateReferencedPaths(CommuniquePdfStorage $storage): void
    {
        $this->eachReferencedPath(
            fn (string $path) => $storage->canonicalPath($path)
        );
    }

    /**
     * Seuls les chemins explicitement référencés par communiques sont touchés.
     * La colonne n'est jamais réécrite, y compris pour uploads/2022/... .
     */
    private function eachReferencedPath(callable $callback): void
    {
        DB::table('communiques')
            ->whereNotNull('fichier')
            ->select(['id', 'fichier'])
            ->orderBy('id')
            ->chunkById(100, function ($communiques) use ($callback): void {
                foreach ($communiques as $communique) {
                    if (trim((string) $communique->fichier) !== '') {
                        $callback((string) $communique->fichier);
                    }
                }
            });
    }
};
