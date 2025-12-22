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
        Schema::create('route_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->time('scheduled_time');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('route_id')
                  ->references('id')
                  ->on('routes')
                  ->onDelete('cascade');

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
            $table->index('is_active');
            $table->index(['route_id', 'scheduled_time']);

            // Prevent duplicate schedule times for same route
            $table->unique(['route_id', 'scheduled_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_schedules');
    }
};
