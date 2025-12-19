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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin',
                'ops_admin',
                'dispatcher',
                'shop_manager',
                'parts_manager',
                'runner_driver',
                'technician_mobile',
                'read_only'
            ])->default('read_only')->after('active');

            $table->foreignId('home_location_id')
                ->nullable()
                ->constrained('service_locations')
                ->nullOnDelete()
                ->after('role');

            $table->json('allowed_location_ids')
                ->nullable()
                ->after('home_location_id')
                ->comment('Array of location IDs this user can access');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['home_location_id']);
            $table->dropColumn(['role', 'home_location_id', 'allowed_location_ids']);
        });
    }
};
