<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medias', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable');
            $table->string('chemin_fichier');
            $table->enum('type', ['image', 'audio', 'video', 'document']);
            $table->integer('taille')->default(0);
            $table->string('type_mime');
            $table->string('texte_alternatif')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medias');
    }
};