<?php

namespace App\Console\Commands;

use App\Services\Wordpress\WordpressImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ImportWordpress extends Command
{
    protected $signature = 'wordpress:import
        {--connection= : Connexion WordPress source}
        {--prefix= : Préfixe des tables WordPress}
        {--dry-run : Inventaire sans aucune écriture dans la cible}
        {--batch=100 : Taille des lots (1 à 1000)}';

    protected $description = 'Importer les contenus WordPress de Septentrional sans modifier la source ni dupliquer les imports précédents';

    public function handle(WordpressImporter $importer): int
    {
        $size = filter_var($this->option('batch'), FILTER_VALIDATE_INT);
        if ($size === false || $size < 1 || $size > 1000) {
            $this->error('La taille des lots doit être comprise entre 1 et 1000.');

            return self::FAILURE;
        }
        try {
            $importer->configure($this->option('connection') ?: config('wordpress.connection'), $this->option('prefix') ?: config('wordpress.prefix'));
            $this->table(['Contenu source', 'Nombre'], collect($importer->inventory())->map(fn ($count, $type) => [$type, $count])->values()->all());
            if ($this->option('dry-run')) {
                $this->info('Simulation terminée : aucune donnée cible modifiée.');

                return self::SUCCESS;
            }
            if (! Schema::hasTable('wordpress_imports')) {
                $this->error('Exécutez les migrations Laravel avant l’import.');

                return self::FAILURE;
            }
            $lock = Cache::lock('septentrional:wordpress-import', 21600);
            if (! $lock->get()) {
                $this->error('Un autre import est déjà en cours.');

                return self::FAILURE;
            }
            try {
                $this->table(['Résultat', 'Nombre'], collect($importer->import($size))->map(fn ($count, $type) => [$type, $count])->values()->all());
            } finally {
                $lock->release();
            }
            $this->info('Import terminé. Les auteurs importés sont désactivés. Les médias restent servis depuis WordPress.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error($exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Import interrompu. Consultez les logs locaux ; une relance reprend les éléments restants.');

            return self::FAILURE;
        }
    }
}
