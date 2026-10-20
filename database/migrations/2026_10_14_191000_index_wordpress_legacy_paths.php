<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_imports', function (Blueprint $table) {
            $table->string('original_path', 512)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('wordpress_imports', function (Blueprint $table) {
            $table->dropColumn('original_path');
        });
    }
};
