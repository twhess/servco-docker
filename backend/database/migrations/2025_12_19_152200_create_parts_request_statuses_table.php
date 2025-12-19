<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_request_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Seed initial data
        DB::table('parts_request_statuses')->insert([
            ['name' => 'new', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'assigned', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'en_route_pickup', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'picked_up', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'en_route_dropoff', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'canceled', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'problem', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_request_statuses');
    }
};
