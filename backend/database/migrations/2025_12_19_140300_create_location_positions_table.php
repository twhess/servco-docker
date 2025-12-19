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
        Schema::create('location_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_location_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable()->comment('Speed in mph or km/h');
            $table->decimal('heading', 5, 2)->nullable()->comment('Compass heading 0-360');
            $table->timestamp('recorded_at');
            $table->enum('source', ['gps', 'manual', 'geofence'])->default('gps');
            $table->timestamps();

            // Indexes
            $table->index('service_location_id');
            $table->index('recorded_at');
            $table->index(['lat', 'lng']);
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_positions');
    }
};
