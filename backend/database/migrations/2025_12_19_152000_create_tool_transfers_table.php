<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_location_id')->constrained('service_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('service_locations')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('transferred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('related_daily_parts_request_id')->nullable()->comment('FK for future parts_requests table');
            $table->enum('status', ['requested', 'in_transit', 'received', 'canceled'])->default('requested');
            $table->timestamp('requested_on');
            $table->timestamp('shipped_on')->nullable();
            $table->timestamp('received_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tool_id');
            $table->index('from_location_id');
            $table->index('to_location_id');
            $table->index('status');
            $table->index('related_daily_parts_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_transfers');
    }
};
