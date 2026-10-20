<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->index(['statut', 'date_publication']);
            $table->index('est_alerte');
            $table->index('nombre_vues');
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->index(['statut', 'date_publication']);
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->index(['statut', 'date_publication']);
            $table->index('nombre_vues');
        });

        Schema::table('emissions', function (Blueprint $table) {
            $table->index('statut');
        });

        Schema::table('commentaires', function (Blueprint $table) {
            $table->index(['commentable_type', 'commentable_id', 'statut']);
            $table->index('statut');
        });

        Schema::table('flash_infos', function (Blueprint $table) {
            $table->index('est_actif');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['statut', 'date_publication']);
            $table->dropIndex(['est_alerte']);
            $table->dropIndex(['nombre_vues']);
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->dropIndex(['statut', 'date_publication']);
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['statut', 'date_publication']);
            $table->dropIndex(['nombre_vues']);
        });

        Schema::table('emissions', function (Blueprint $table) {
            $table->dropIndex(['statut']);
        });

        Schema::table('commentaires', function (Blueprint $table) {
            $table->dropIndex(['commentable_type', 'commentable_id', 'statut']);
            $table->dropIndex(['statut']);
        });

        Schema::table('flash_infos', function (Blueprint $table) {
            $table->dropIndex(['est_actif']);
        });
    }
};
