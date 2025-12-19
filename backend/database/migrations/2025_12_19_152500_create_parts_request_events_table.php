<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parts_request_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', [
                'created',
                'assigned',
                'unassigned',
                'started',
                'arrived_pickup',
                'picked_up',
                'departed_pickup',
                'arrived_dropoff',
                'delivered',
                'canceled',
                'problem_reported',
                'note_added',
                'status_changed'
            ]);
            $table->timestamp('event_at');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->index('parts_request_id');
            $table->index('event_type');
            $table->index('event_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_request_events');
    }
};
