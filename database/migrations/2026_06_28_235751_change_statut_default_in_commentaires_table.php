<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Passer au mode « approuvé par défaut ».
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite ne prend pas en charge ALTER TABLE ... MODIFY ... ENUM.
        // Dans les tests GitHub, on met seulement à jour les données existantes.
        if ($driver === 'sqlite') {
            DB::table('commentaires')
                ->where('statut', 'en_attente')
                ->update(['statut' => 'approuve']);

            return;
        }

        // Production MySQL/MariaDB
        DB::statement(
            "ALTER TABLE commentaires
             MODIFY statut ENUM('en_attente','approuve','rejete')
             NOT NULL DEFAULT 'approuve'"
        );

        DB::table('commentaires')
            ->where('statut', 'en_attente')
            ->update(['statut' => 'approuve']);
    }

    /**
     * Revenir à la modération préalable.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE commentaires
             MODIFY statut ENUM('en_attente','approuve','rejete')
             NOT NULL DEFAULT 'en_attente'"
        );
    }
};