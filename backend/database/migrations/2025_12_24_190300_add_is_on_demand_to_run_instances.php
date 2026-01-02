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
        Schema::table('run_instances', function (Blueprint $table) {
            // Flag to indicate if this run was created on-demand (vs from a scheduled schedule)
            $table->boolean('is_on_demand')->default(false)->after('route_schedule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('run_instances', function (Blueprint $table) {
            $table->dropColumn('is_on_demand');
        });
    }
};
