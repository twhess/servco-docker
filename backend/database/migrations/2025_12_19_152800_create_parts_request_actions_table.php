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
        Schema::create('parts_request_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_type_id');
            $table->unsignedBigInteger('from_status_id');
            $table->string('action_name');
            $table->unsignedBigInteger('to_status_id');
            $table->enum('actor_role', ['shop_staff', 'runner', 'dispatcher']);
            $table->boolean('requires_note')->default(false);
            $table->boolean('requires_photo')->default(false);
            $table->string('display_label');
            $table->string('display_color')->nullable();
            $table->string('display_icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('request_type_id')
                  ->references('id')
                  ->on('parts_request_types')
                  ->onDelete('cascade');

            $table->foreign('from_status_id')
                  ->references('id')
                  ->on('parts_request_statuses')
                  ->onDelete('cascade');

            $table->foreign('to_status_id')
                  ->references('id')
                  ->on('parts_request_statuses')
                  ->onDelete('cascade');

            // Indexes for performance
            $table->index('request_type_id');
            $table->index('from_status_id');
            $table->index('actor_role');
            $table->index('is_active');
            $table->index(['request_type_id', 'from_status_id', 'actor_role'], 'pra_type_status_role_idx');

            // Ensure unique action per type/status/name combination
            $table->unique(['request_type_id', 'from_status_id', 'action_name'], 'pra_type_status_name_unq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts_request_actions');
    }
};
