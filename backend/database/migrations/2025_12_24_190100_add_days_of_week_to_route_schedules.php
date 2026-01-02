<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('route_schedules', function (Blueprint $table) {
            // Days of week: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
            // Default to weekdays [1,2,3,4,5]
            $table->json('days_of_week')->nullable()->after('is_active');
        });

        // Update existing schedules to have weekdays
        DB::table('route_schedules')->update([
            'days_of_week' => json_encode([1, 2, 3, 4, 5]),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('route_schedules', function (Blueprint $table) {
            $table->dropColumn('days_of_week');
        });
    }
};
