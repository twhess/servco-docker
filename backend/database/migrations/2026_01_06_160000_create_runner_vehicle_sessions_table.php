<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tracks which vehicle a runner is using for their session/run.
     */
    public function up(): void
    {
        Schema::create('runner_vehicle_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('run_id')->nullable();
            $table->unsignedBigInteger('vehicle_location_id')->nullable()->comment('FK to service_locations for known vehicles');

            // For generic/unknown vehicles
            $table->boolean('is_generic')->default(false);
            $table->string('generic_vehicle_type', 50)->nullable()->comment('car, truck, van, suv, other');
            $table->string('generic_vehicle_description', 255)->nullable()->comment('e.g. White Ford F-150');
            $table->string('generic_license_plate', 20)->nullable();

            $table->datetime('started_at');
            $table->datetime('ended_at')->nullable();

            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('run_id')->references('id')->on('run_instances')->onDelete('set null');
            $table->foreign('vehicle_location_id')->references('id')->on('service_locations')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['user_id', 'started_at'], 'rvs_user_started_idx');
            $table->index(['run_id'], 'rvs_run_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runner_vehicle_sessions');
    }
};
