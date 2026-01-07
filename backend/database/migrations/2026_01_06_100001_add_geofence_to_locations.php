<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_locations', function (Blueprint $table) {
            // Geofence radius in meters (default 200m)
            // Note: latitude and longitude columns already exist on service_locations
            $table->unsignedInteger('geofence_radius_m')->default(200)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('service_locations', function (Blueprint $table) {
            $table->dropColumn('geofence_radius_m');
        });
    }
};
