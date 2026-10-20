<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('categorie_id')->constrained('categories')->onDelete('restrict');
            $table->string('titre');
            $table->string('slug')->unique();
            $table->text('extrait');
            $table->longText('contenu');
            $table->string('image_principale')->nullable();
            $table->enum('statut', ['brouillon', 'publie', 'archive'])->default('brouillon');
            $table->boolean('est_alerte')->default(false);
            $table->integer('nombre_vues')->default(0);
            $table->string('meta_titre')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('date_publication')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};