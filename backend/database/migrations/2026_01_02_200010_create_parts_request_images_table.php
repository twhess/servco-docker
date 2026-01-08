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
        Schema::create('parts_request_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parts_request_id')->constrained()->cascadeOnDelete();

            // Image source: 'requester' (info images), 'pickup' (runner at pickup), 'delivery' (runner at delivery)
            $table->enum('source', ['requester', 'pickup', 'delivery'])->default('requester');

            // File info
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable(); // Smaller version for listings
            $table->string('mime_type');
            $table->unsignedInteger('file_size'); // After compression
            $table->unsignedInteger('original_size')->nullable(); // Before compression
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();

            // Metadata
            $table->string('caption')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('taken_at')->nullable();

            // Audit
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['parts_request_id', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts_request_images');
    }
};
