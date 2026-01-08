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
        Schema::create('route_graph_cache', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_location_id');
            $table->unsignedBigInteger('to_location_id');
            $table->json('path_json');
            $table->integer('hop_count');
            $table->integer('estimated_duration_minutes')->nullable();
            $table->boolean('requires_manual_routing')->default(false);
            $table->timestamp('cached_at');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('from_location_id')
                  ->references('id')
                  ->on('service_locations')
                  ->onDelete('cascade');

            $table->foreign('to_location_id')
                  ->references('id')
                  ->on('service_locations')
                  ->onDelete('cascade');

            // Indexes for performance
            $table->index('from_location_id');
            $table->index('to_location_id');
            $table->index('cached_at');
            $table->index('requires_manual_routing');

            // Ensure unique cache entry per location pair
            $table->unique(['from_location_id', 'to_location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_graph_cache');
    }
};
