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
        Schema::create('service_locations', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('name');
            $table->string('code')->unique()->comment('Short internal identifier');
            $table->enum('location_type', [
                'fixed_shop',
                'mobile_service_truck',
                'parts_runner_vehicle',
                'vendor',
                'customer_site'
            ]);
            $table->enum('status', [
                'available',
                'on_job',
                'on_run',
                'offline',
                'maintenance'
            ])->default('available');
            $table->boolean('is_active')->default(true);
            $table->string('timezone')->default('America/New_York');
            $table->text('notes')->nullable();

            // Fixed location fields (nullable for mobile)
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 50)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('US');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Mobile / vehicle fields (nullable for fixed)
            $table->unsignedBigInteger('vehicle_asset_id')->nullable()->comment('FK placeholder for future assets table');
            $table->unsignedBigInteger('home_base_location_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->decimal('last_known_lat', 10, 7)->nullable();
            $table->decimal('last_known_lng', 10, 7)->nullable();
            $table->timestamp('last_known_at')->nullable();
            $table->boolean('is_dispatchable')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('location_type');
            $table->index('status');
            $table->index('is_active');
            $table->index(['latitude', 'longitude']);
            $table->index(['last_known_lat', 'last_known_lng']);

            // Foreign keys
            $table->foreign('home_base_location_id')
                ->references('id')
                ->on('service_locations')
                ->nullOnDelete();

            $table->foreign('assigned_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_locations');
    }
};
