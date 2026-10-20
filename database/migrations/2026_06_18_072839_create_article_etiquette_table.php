<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_etiquette', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('articles')->onDelete('cascade');
            $table->foreignId('etiquette_id')->constrained('etiquettes')->onDelete('cascade');
            $table->primary(['article_id', 'etiquette_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_etiquette');
    }
};