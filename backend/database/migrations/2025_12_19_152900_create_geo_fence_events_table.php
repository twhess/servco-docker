<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_fence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geo_fence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parts_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('runner_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('event_type', ['entered', 'exited', 'arrived']);
            $table->timestamp('event_at');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy_m', 8, 2)->nullable();

            $table->index('geo_fence_id');
            $table->index('parts_request_id');
            $table->index('runner_user_id');
            $table->index('event_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_fence_events');
    }
};
