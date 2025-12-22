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
        Schema::create('run_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_instance_id');
            $table->enum('note_type', ['general', 'delay', 'issue', 'completion']);
            $table->text('notes');
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('run_instance_id')
                  ->references('id')
                  ->on('run_instances')
                  ->onDelete('cascade');

            $table->foreign('created_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            // Indexes for performance
            $table->index('run_instance_id');
            $table->index('note_type');
            $table->index(['run_instance_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_notes');
    }
};
