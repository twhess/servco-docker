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
        Schema::create('run_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->date('scheduled_date');
            $table->time('scheduled_time');
            $table->unsignedBigInteger('assigned_runner_user_id')->nullable();
            $table->unsignedBigInteger('assigned_vehicle_location_id')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'canceled'])->default('pending');
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();
            $table->unsignedBigInteger('current_stop_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('route_id')
                  ->references('id')
                  ->on('routes')
                  ->onDelete('restrict');

            $table->foreign('assigned_runner_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('assigned_vehicle_location_id')
                  ->references('id')
                  ->on('service_locations')
                  ->onDelete('set null');

            $table->foreign('current_stop_id')
                  ->references('id')
                  ->on('route_stops')
                  ->onDelete('set null');

            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            $table->foreign('updated_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            // Indexes for performance
            $table->index('route_id');
            $table->index('scheduled_date');
            $table->index('status');
            $table->index('assigned_runner_user_id');
            $table->index(['route_id', 'scheduled_date', 'scheduled_time']);
            $table->index(['scheduled_date', 'status']);

            // Prevent duplicate run instances for same route/date/time
            $table->unique(['route_id', 'scheduled_date', 'scheduled_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_instances');
    }
};
