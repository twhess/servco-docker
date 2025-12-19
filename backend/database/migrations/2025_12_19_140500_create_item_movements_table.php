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
        Schema::create('item_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('service_locations');
            $table->foreignId('to_location_id')->constrained('service_locations');
            $table->timestamp('moved_at');
            $table->foreignId('moved_by_user_id')->constrained('users');
            $table->unsignedBigInteger('request_id')->nullable()->comment('FK placeholder for future requests table');
            $table->unsignedBigInteger('photo_id')->nullable()->comment('FK placeholder for future photos/attachments table');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('item_id');
            $table->index('from_location_id');
            $table->index('to_location_id');
            $table->index('moved_at');
            $table->index('moved_by_user_id');
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_movements');
    }
};
