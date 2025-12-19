<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urgency_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Seed initial data
        DB::table('urgency_levels')->insert([
            ['name' => 'normal', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'today', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'asap', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'emergency', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('urgency_levels');
    }
};
