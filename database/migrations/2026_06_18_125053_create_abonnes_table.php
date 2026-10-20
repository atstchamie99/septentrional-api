<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnes', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('email')->unique();
            $table->enum('statut', ['actif', 'desabonne'])->default('actif');
            $table->string('jeton');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnes');
    }
};