<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('occasions_speciales', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('texte')->nullable();
            $table->string('image')->nullable();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('est_actif')->default(true);
            $table->timestamps();

            $table->index(['est_actif', 'date_debut', 'date_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('occasions_speciales');
    }
};
