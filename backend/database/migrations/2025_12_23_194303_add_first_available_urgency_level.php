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
        // Add 'first_available' as the new default urgency level
        // Insert it with a lower ID-like ordering so it appears first in lists
        DB::table('urgency_levels')->insert([
            'name' => 'first_available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('urgency_levels')->where('name', 'first_available')->delete();
    }
};
