<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();

            // Gmail identifiers
            $table->string('gmail_message_id')->unique();
            $table->string('gmail_thread_id')->index();

            // Email metadata
            $table->string('subject', 500)->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to_emails');
            $table->timestamp('email_date');
            $table->text('snippet')->nullable();

            // Content
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            // Attachment tracking
            $table->boolean('has_attachments')->default(false)->index();
            $table->unsignedInteger('attachment_count')->default(0);

            // Processing status
            $table->enum('status', ['unprocessed', 'processing', 'processed', 'error'])
                ->default('unprocessed')
                ->index();
            $table->text('processing_notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            // Additional indexes
            $table->index('from_email');
            $table->index('email_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
