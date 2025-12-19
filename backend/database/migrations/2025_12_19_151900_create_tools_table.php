<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('tool_categories')->restrictOnDelete();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->enum('status', ['available', 'in_use', 'maintenance', 'lost', 'retired'])->default('available');
            $table->foreignId('home_location_id')->constrained('service_locations')->restrictOnDelete();
            $table->foreignId('current_location_id')->constrained('service_locations')->restrictOnDelete();
            $table->foreignId('current_area_id')->nullable()->constrained('location_areas')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('asset_tag');
            $table->index('status');
            $table->index('home_location_id');
            $table->index('current_location_id');
            $table->index('assigned_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
