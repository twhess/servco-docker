<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add "confirmed" status for auto-assigned transfers
        DB::table('parts_request_statuses')->insert([
            'name' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('parts_request_statuses')->where('name', 'confirmed')->delete();
    }
};
