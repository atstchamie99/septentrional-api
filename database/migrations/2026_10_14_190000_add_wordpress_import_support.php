<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // categorie_id reste la catégorie principale pour les clients existants.
        Schema::create('article_categorie', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('categorie_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['article_id', 'categorie_id']);
            $table->index('categorie_id');
        });

        Schema::create('wordpress_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source', 100);
            $table->string('entity', 30);
            $table->unsignedBigInteger('wordpress_id');
            $table->string('target_type', 100);
            $table->unsignedBigInteger('target_id');
            $table->text('original_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('imported_at');
            $table->unique(['source', 'entity', 'wordpress_id']);
            $table->index(['target_type', 'target_id']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('meta_titre')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('date_publication')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['meta_titre', 'meta_description', 'date_publication']);
        });
        Schema::dropIfExists('wordpress_imports');
        Schema::dropIfExists('article_categorie');
    }
};
