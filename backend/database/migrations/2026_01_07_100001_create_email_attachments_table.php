<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('email_message_id')
                ->constrained('email_messages')
                ->cascadeOnDelete();

            // Gmail identifier
            $table->string('gmail_attachment_id');

            // File info
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');

            // Drive storage (after download)
            $table->string('drive_file_id')->nullable()->index();
            $table->string('drive_web_view_link', 500)->nullable();
            $table->string('drive_download_link', 500)->nullable();

            // Processing status
            $table->enum('status', ['pending', 'downloaded', 'error'])
                ->default('pending')
                ->index();
            $table->text('error_message')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->foreignId('downloaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
