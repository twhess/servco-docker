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
        Schema::table('parts_requests', function (Blueprint $table) {
            // Run assignment fields
            $table->unsignedBigInteger('run_instance_id')->nullable()->after('id');
            $table->unsignedBigInteger('pickup_stop_id')->nullable()->after('run_instance_id');
            $table->unsignedBigInteger('dropoff_stop_id')->nullable()->after('pickup_stop_id');

            // Multi-leg segment fields
            $table->unsignedBigInteger('parent_request_id')->nullable()->after('dropoff_stop_id');
            $table->integer('segment_order')->nullable()->after('parent_request_id');
            $table->boolean('is_segment')->default(false)->after('segment_order');

            // Inventory tracking field
            $table->unsignedBigInteger('item_id')->nullable()->after('is_segment');

            // Forward scheduling fields
            $table->date('scheduled_for_date')->nullable()->after('item_id');
            $table->timestamp('not_before_datetime')->nullable()->after('scheduled_for_date');

            // Admin override fields
            $table->unsignedBigInteger('override_run_instance_id')->nullable()->after('not_before_datetime');
            $table->text('override_reason')->nullable()->after('override_run_instance_id');
            $table->unsignedBigInteger('override_by_user_id')->nullable()->after('override_reason');
            $table->timestamp('override_at')->nullable()->after('override_by_user_id');

            // Archiving fields
            $table->boolean('is_archived')->default(false)->after('override_at');
            $table->timestamp('archived_at')->nullable()->after('is_archived');

            // Foreign key constraints
            $table->foreign('run_instance_id')
                  ->references('id')
                  ->on('run_instances')
                  ->onDelete('set null');

            $table->foreign('pickup_stop_id')
                  ->references('id')
                  ->on('route_stops')
                  ->onDelete('set null');

            $table->foreign('dropoff_stop_id')
                  ->references('id')
                  ->on('route_stops')
                  ->onDelete('set null');

            $table->foreign('parent_request_id')
                  ->references('id')
                  ->on('parts_requests')
                  ->onDelete('cascade');

            $table->foreign('item_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('set null');

            $table->foreign('override_run_instance_id')
                  ->references('id')
                  ->on('run_instances')
                  ->onDelete('set null');

            $table->foreign('override_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes for performance
            $table->index('run_instance_id');
            $table->index('pickup_stop_id');
            $table->index('dropoff_stop_id');
            $table->index('parent_request_id');
            $table->index('item_id');
            $table->index('scheduled_for_date');
            $table->index('is_archived');
            $table->index(['run_instance_id', 'pickup_stop_id']);
            $table->index(['parent_request_id', 'segment_order']);
            $table->index(['scheduled_for_date', 'is_archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parts_requests', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['run_instance_id']);
            $table->dropForeign(['pickup_stop_id']);
            $table->dropForeign(['dropoff_stop_id']);
            $table->dropForeign(['parent_request_id']);
            $table->dropForeign(['item_id']);
            $table->dropForeign(['override_run_instance_id']);
            $table->dropForeign(['override_by_user_id']);

            // Drop columns
            $table->dropColumn([
                'run_instance_id',
                'pickup_stop_id',
                'dropoff_stop_id',
                'parent_request_id',
                'segment_order',
                'is_segment',
                'item_id',
                'scheduled_for_date',
                'not_before_datetime',
                'override_run_instance_id',
                'override_reason',
                'override_by_user_id',
                'override_at',
                'is_archived',
                'archived_at',
            ]);
        });
    }
};
