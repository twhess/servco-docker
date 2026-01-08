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
        Schema::create('vendor_cluster_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_stop_id');
            $table->unsignedBigInteger('vendor_location_id');
            $table->integer('location_order')->default(0);
            $table->boolean('is_optional')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('route_stop_id')
                  ->references('id')
                  ->on('route_stops')
                  ->onDelete('cascade');

            $table->foreign('vendor_location_id')
                  ->references('id')
                  ->on('service_locations')
                  ->onDelete('restrict');

            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            $table->foreign('updated_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            // Indexes for performance
            $table->index('route_stop_id');
            $table->index('vendor_location_id');
            $table->index(['route_stop_id', 'location_order']);

            // Ensure unique vendor per cluster stop
            $table->unique(['route_stop_id', 'vendor_location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_cluster_locations');
    }
};
