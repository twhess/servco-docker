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
        Schema::table('service_locations', function (Blueprint $table) {
            // Color fields for visual identification of shops and mobile units
            $table->string('text_color', 7)->nullable()->after('notes')->comment('Hex color for text/foreground (e.g., #FFFFFF)');
            $table->string('background_color', 7)->nullable()->after('text_color')->comment('Hex color for background (e.g., #1976D2)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_locations', function (Blueprint $table) {
            $table->dropColumn(['text_color', 'background_color']);
        });
    }
};
