<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->unsignedBigInteger('nombre_ecoutes')->default(0)->after('duree');
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

            $table->unique(
                ['episode_id', 'fingerprint_hash', 'session_date'],
                'episode_listen_daily_unique'
            );
            $table->index(['episode_id', 'qualified_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_listen_sessions');

        Schema::table('episodes', function (Blueprint $table) {
            $table->dropIndex(['nombre_ecoutes']);
            $table->dropColumn('nombre_ecoutes');
        });
    }
};
