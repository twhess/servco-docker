<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_request_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parts_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('content');

            // Author
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Edit tracking
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();

            // Audit fields
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index('parts_request_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_request_notes');
    }
};
