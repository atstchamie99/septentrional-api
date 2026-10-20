<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('categorie_id')->constrained('categories')->onDelete('restrict');
            $table->string('titre');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('miniature')->nullable();
            $table->string('url_video');
            $table->enum('type_video', ['upload', 'youtube', 'vimeo'])->default('youtube');
            $table->integer('duree')->default(0);
            $table->enum('statut', ['brouillon', 'publie', 'archive'])->default('brouillon');
            $table->integer('nombre_vues')->default(0);
            $table->timestamp('date_publication')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};