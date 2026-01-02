<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by');
            $table->string('file_path');
            $table->string('original_filename');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');

            // Counts
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('merge_needed_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Summary
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_imports');
    }
};
