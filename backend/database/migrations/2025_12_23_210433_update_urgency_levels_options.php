<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get the first_available urgency level ID
        $firstAvailable = DB::table('urgency_levels')->where('name', 'first_available')->first();

        if ($firstAvailable) {
            // Get IDs of urgency levels to be removed
            $toRemoveIds = DB::table('urgency_levels')
                ->whereIn('name', ['asap', 'normal', 'emergency'])
                ->pluck('id');

            // Reassign any parts_requests using these urgency levels to first_available
            DB::table('parts_requests')
                ->whereIn('urgency_id', $toRemoveIds)
                ->update(['urgency_id' => $firstAvailable->id]);
        }

        // Remove ASAP, Normal, and Emergency urgency levels
        DB::table('urgency_levels')->whereIn('name', ['asap', 'normal', 'emergency'])->delete();

        // Add Saturday urgency level if it doesn't exist
        if (!DB::table('urgency_levels')->where('name', 'saturday')->exists()) {
            DB::table('urgency_levels')->insert([
                'name' => 'saturday',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Add Next Business Day urgency level if it doesn't exist
        if (!DB::table('urgency_levels')->where('name', 'next_business_day')->exists()) {
            DB::table('urgency_levels')->insert([
                'name' => 'next_business_day',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove Saturday and Next Business Day
        DB::table('urgency_levels')->whereIn('name', ['saturday', 'next_business_day'])->delete();

        // Re-add the removed urgency levels
        $levels = ['normal', 'asap', 'emergency'];
        foreach ($levels as $level) {
            if (!DB::table('urgency_levels')->where('name', $level)->exists()) {
                DB::table('urgency_levels')->insert([
                    'name' => $level,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
