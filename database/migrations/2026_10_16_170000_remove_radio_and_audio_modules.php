<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Nettoyer les commentaires polymorphiques liés aux épisodes et émissions
        if (Schema::hasTable('commentaires')) {
            DB::table('commentaires')
                ->whereIn('commentable_type', [
                    'App\Models\Episode',
                    'App\Models\Emission',
                ])
                ->delete();
        }

        // 2. Supprimer les tables dépendantes d'abord (clés étrangères)
        Schema::dropIfExists('episode_listen_sessions');
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('emissions');
        Schema::dropIfExists('flux_radios');
        Schema::dropIfExists('programmes');
    }

    public function down(): void
    {
        Schema::create('emissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('titre');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->enum('jour_diffusion', ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'])->nullable();
            $table->time('heure_diffusion')->nullable();
            $table->enum('statut', ['brouillon', 'publie', 'archive'])->default('brouillon');
            $table->timestamps();
            $table->index('statut');
        });

        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emission_id')->constrained('emissions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('titre');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('fichier_audio');
            $table->integer('duree')->nullable();
            $table->unsignedBigInteger('nombre_ecoutes')->default(0);
            $table->string('image')->nullable();
            $table->timestamp('date_publication')->nullable();
            $table->enum('statut', ['brouillon', 'publie', 'archive'])->default('brouillon');
            $table->string('type_mime')->default('audio/mpeg');
            $table->timestamps();
            $table->index(['statut', 'date_publication']);
            $table->index('nombre_ecoutes');
        });

        Schema::create('episode_listen_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('fingerprint_hash', 64);
            $table->date('session_date');
            $table->timestamp('started_at');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['episode_id', 'fingerprint_hash', 'session_date'], 'episode_listen_daily_unique');
            $table->index(['episode_id', 'qualified_at']);
            $table->index('expires_at');
        });

        Schema::create('flux_radios', function (Blueprint $table) {
            $table->id();
            $table->string('nom_station');
            $table->string('url_flux');
            $table->string('url_secours')->nullable();
            $table->string('emission_actuelle')->nullable();
            $table->string('animateur')->nullable();
            $table->boolean('est_en_direct')->default(false);
            $table->timestamps();
        });

        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->enum('jour', ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche']);
            $table->time('heure_debut');
            $table->time('heure_fin')->nullable();
            $table->string('titre');
            $table->enum('langue', ['francais', 'kabye', 'ewe', 'anglais'])->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->index(['jour', 'heure_debut']);
        });
    }
};
