<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runner_locations', function (Blueprint $table) {
            $table->id();

            // Runner who reported this location
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Optional: which run this location is associated with
            $table->foreignId('run_id')->nullable()->constrained('run_instances')->onDelete('set null');

            // GPS coordinates
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            // Accuracy in meters (from device GPS)
            $table->unsignedInteger('accuracy_m')->nullable();

            // When the location was recorded (may differ from created_at)
            $table->dateTime('recorded_at');

            // Standard timestamps
            $table->timestamps();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            // Indexes for querying location history (short names for MySQL)
            $table->index(['user_id', 'recorded_at'], 'rl_user_recorded_idx');
            $table->index(['run_id', 'recorded_at'], 'rl_run_recorded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runner_locations');
    }
};
