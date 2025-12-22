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
        Schema::create('run_stop_actuals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_instance_id');
            $table->unsignedBigInteger('route_stop_id');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->integer('tasks_completed')->default(0);
            $table->integer('tasks_total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('run_instance_id')
                  ->references('id')
                  ->on('run_instances')
                  ->onDelete('cascade');

            $table->foreign('route_stop_id')
                  ->references('id')
                  ->on('route_stops')
                  ->onDelete('cascade');

            // Indexes for performance
            $table->index('run_instance_id');
            $table->index('route_stop_id');
            $table->index(['run_instance_id', 'arrived_at']);
            $table->index(['route_stop_id', 'arrived_at']);

            // Ensure unique record per run + stop combination
            $table->unique(['run_instance_id', 'route_stop_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_stop_actuals');
    }
};
