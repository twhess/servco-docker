<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->string('serial_number')->unique()->nullable();
            $table->foreignId('device_type_id')->constrained()->restrictOnDelete();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('hostname')->nullable();
            $table->string('function')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->string('vendor_name')->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->enum('status', ['active', 'inactive', 'repair', 'retired', 'lost'])->default('active');
            $table->foreignId('home_location_id')->constrained('service_locations')->restrictOnDelete();
            $table->foreignId('current_location_id')->nullable()->constrained('service_locations')->nullOnDelete();
            $table->foreignId('current_area_id')->nullable()->constrained('location_areas')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('asset_tag');
            $table->index('serial_number');
            $table->index('device_type_id');
            $table->index('status');
            $table->index('home_location_id');
            $table->index('current_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
