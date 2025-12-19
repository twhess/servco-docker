<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_request_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Seed initial data
        DB::table('parts_request_types')->insert([
            ['name' => 'pickup', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'delivery', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'transfer', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_request_types');
    }
};
