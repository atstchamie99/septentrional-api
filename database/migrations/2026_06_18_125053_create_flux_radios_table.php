<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flux_radios', function (Blueprint $table) {
            $table->id();
            $table->string('url_flux');
            $table->string('url_secours')->nullable();
            $table->string('nom_station');
            $table->boolean('est_en_direct')->default(false);
            $table->string('emission_actuelle')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flux_radios');
    }
};