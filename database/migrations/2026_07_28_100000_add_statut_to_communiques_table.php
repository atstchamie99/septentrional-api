<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communiques', function (Blueprint $table) {
            // Les communiqués historiques étaient tous publics. Le défaut "publie"
            // préserve ce comportement lors de la migration.
            $table->enum('statut', ['brouillon', 'publie', 'archive'])
                ->default('publie')
                ->after('fichier');

            $table->index(
                ['statut', 'date_publication', 'date_expiration'],
                'communiques_publication_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('communiques', function (Blueprint $table) {
            $table->dropIndex('communiques_publication_index');
            $table->dropColumn('statut');
        });
    }
};
