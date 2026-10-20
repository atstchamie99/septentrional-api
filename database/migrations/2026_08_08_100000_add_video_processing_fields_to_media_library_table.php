<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media_library', function (Blueprint $table): void {
            $table->boolean('processing')->default(false)->after('size');
            $table->string('processing_path')->nullable()->after('processing');
            $table->string('processing_error')->nullable()->after('processing_path');
        });
    }

    public function down(): void
    {
        Schema::table('media_library', function (Blueprint $table): void {
            $table->dropColumn(['processing', 'processing_path', 'processing_error']);
        });
    }
};
