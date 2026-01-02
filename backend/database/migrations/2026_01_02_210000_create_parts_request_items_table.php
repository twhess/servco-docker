<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parts_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('part_number')->nullable();
            $table->text('notes')->nullable();

            // Runner verification
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            // Ordering
            $table->unsignedInteger('sort_order')->default(0);

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
            $table->index('is_verified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_request_items');
    }
};
