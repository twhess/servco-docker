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
        // Add name field to route_schedules
        Schema::table('route_schedules', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('scheduled_time');
        });

        // Add route_schedule_id to run_instances for proper linking
        Schema::table('run_instances', function (Blueprint $table) {
            $table->unsignedBigInteger('route_schedule_id')->nullable()->after('scheduled_time');

            $table->foreign('route_schedule_id')
                  ->references('id')
                  ->on('route_schedules')
                  ->onDelete('set null');

            $table->index('route_schedule_id');
        });

        // Generate default names for existing schedules based on time
        DB::table('route_schedules')->get()->each(function ($schedule) {
            $hour = (int) substr($schedule->scheduled_time, 0, 2);
            $name = match (true) {
                $hour >= 5 && $hour < 12 => 'Morning',
                $hour >= 12 && $hour < 17 => 'Afternoon',
                $hour >= 17 && $hour < 21 => 'Evening',
                default => 'Night',
            };

            DB::table('route_schedules')
                ->where('id', $schedule->id)
                ->update(['name' => $name]);
        });

        // Link existing run_instances to their schedules
        DB::table('run_instances')->get()->each(function ($run) {
            $schedule = DB::table('route_schedules')
                ->where('route_id', $run->route_id)
                ->where('scheduled_time', $run->scheduled_time)
                ->first();

            if ($schedule) {
                DB::table('run_instances')
                    ->where('id', $run->id)
                    ->update(['route_schedule_id' => $schedule->id]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('run_instances', function (Blueprint $table) {
            $table->dropForeign(['route_schedule_id']);
            $table->dropColumn('route_schedule_id');
        });

        Schema::table('route_schedules', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
