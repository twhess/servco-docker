<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_location_id')->nullable()->constrained('service_locations')->nullOnDelete();
            $table->foreignId('assigned_area_id')->nullable()->constrained('location_areas')->nullOnDelete();
            $table->timestamp('assigned_on');
            $table->timestamp('returned_on')->nullable();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('device_id');
            $table->index('assigned_to_user_id');
            $table->index('assigned_to_location_id');
            $table->index(['assigned_on', 'returned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_assignments');
    }
};
