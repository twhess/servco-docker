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
        Schema::table('route_schedules', function (Blueprint $table) {
            // Schedule types: 'fixed' = runs on scheduled days/times, 'on_demand' = created manually as needed
            $table->enum('schedule_type', ['fixed', 'on_demand'])->default('fixed')->after('days_of_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('route_schedules', function (Blueprint $table) {
            $table->dropColumn('schedule_type');
        });
    }
};
