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
        Schema::table('vendor_cluster_locations', function (Blueprint $table) {
            // Drop the old foreign key constraint
            $table->dropForeign(['vendor_location_id']);

            // Rename column to vendor_id for clarity
            $table->renameColumn('vendor_location_id', 'vendor_id');
        });

        Schema::table('vendor_cluster_locations', function (Blueprint $table) {
            // Add new foreign key to vendors table
            $table->foreign('vendor_id')
                  ->references('id')
                  ->on('vendors')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_cluster_locations', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['vendor_id']);

            // Rename column back
            $table->renameColumn('vendor_id', 'vendor_location_id');
        });

        Schema::table('vendor_cluster_locations', function (Blueprint $table) {
            // Restore original foreign key to service_locations
            $table->foreign('vendor_location_id')
                  ->references('id')
                  ->on('service_locations')
                  ->onDelete('restrict');
        });
    }
};
