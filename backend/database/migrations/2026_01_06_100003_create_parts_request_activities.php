<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_request_activities', function (Blueprint $table) {
            $table->id();

            // The parts request this activity belongs to
            $table->foreignId('parts_request_id')->constrained()->onDelete('cascade');

            // Status transition
            $table->foreignId('from_status_id')->nullable()->constrained('parts_request_statuses')->onDelete('set null');
            $table->foreignId('to_status_id')->constrained('parts_request_statuses')->onDelete('cascade');

            // Who performed the action
            $table->foreignId('actor_user_id')->constrained('users')->onDelete('cascade');

            // Optional notes (required for exceptions)
            $table->text('notes')->nullable();

            // Context: where/when the action occurred
            $table->foreignId('stop_id')->nullable()->constrained('route_stops')->onDelete('set null');
            $table->foreignId('run_id')->nullable()->constrained('run_instances')->onDelete('set null');

            // Standard timestamps
            $table->timestamps();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            // Index for querying activity history (short name for MySQL)
            $table->index(['parts_request_id', 'created_at'], 'pra_request_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_request_activities');
    }
};
