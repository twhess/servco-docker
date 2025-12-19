<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_type_id')->constrained('parts_request_types')->restrictOnDelete();
            $table->string('reference_number')->unique();

            // Vendor/Customer details
            $table->string('vendor_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('customer_address')->nullable();
            $table->decimal('customer_lat', 10, 7)->nullable();
            $table->decimal('customer_lng', 10, 7)->nullable();

            // Origin location
            $table->foreignId('origin_location_id')->nullable()->constrained('service_locations')->nullOnDelete();
            $table->foreignId('origin_area_id')->nullable()->constrained('location_areas')->nullOnDelete();
            $table->text('origin_address')->nullable();
            $table->decimal('origin_lat', 10, 7)->nullable();
            $table->decimal('origin_lng', 10, 7)->nullable();

            // Receiving location
            $table->foreignId('receiving_location_id')->nullable()->constrained('service_locations')->nullOnDelete();
            $table->foreignId('receiving_area_id')->nullable()->constrained('location_areas')->nullOnDelete();

            // Request details
            $table->timestamp('requested_at');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('pickup_run')->default(false);
            $table->foreignId('urgency_id')->constrained('urgency_levels')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('parts_request_statuses')->restrictOnDelete();
            $table->text('details');
            $table->text('special_instructions')->nullable();

            // Slack notifications
            $table->boolean('slack_notify_pickup')->default(false);
            $table->boolean('slack_notify_delivery')->default(false);
            $table->string('slack_channel')->nullable();

            // Assignment
            $table->foreignId('assigned_runner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            // Tracking
            $table->foreignId('last_modified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_modified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('reference_number');
            $table->index('request_type_id');
            $table->index('status_id');
            $table->index('urgency_id');
            $table->index('assigned_runner_user_id');
            $table->index('requested_at');
            $table->index('origin_location_id');
            $table->index('receiving_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_requests');
    }
};
