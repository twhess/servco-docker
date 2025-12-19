<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_request_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parts_request_id')->constrained()->cascadeOnDelete();
            $table->enum('stage', ['pickup', 'delivery', 'other']);
            $table->string('file_path');
            $table->timestamp('taken_at');
            $table->foreignId('taken_by_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at');

            $table->index('parts_request_id');
            $table->index('stage');
            $table->index('taken_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_request_photos');
    }
};
