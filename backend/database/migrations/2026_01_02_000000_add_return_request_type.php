<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add the 'return' request type for returning parts to vendors
        DB::table('parts_request_types')->insert([
            'name' => 'return',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('parts_request_types')->where('name', 'return')->delete();
    }
};
