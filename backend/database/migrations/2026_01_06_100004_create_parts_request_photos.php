<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table already exists from 2025_12_19_152600_create_parts_request_photos_table.php
        // Add missing columns for runner activity tracking
        Schema::table('parts_request_photos', function (Blueprint $table) {
            // Link to a specific activity (e.g., pickup, delivery status change)
            if (!Schema::hasColumn('parts_request_photos', 'activity_id')) {
                $table->foreignId('activity_id')->nullable()->after('parts_request_id')
                    ->constrained('parts_request_activities')->onDelete('set null');
            }

            // Add updated_at if missing
            if (!Schema::hasColumn('parts_request_photos', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }

            // Add audit fields if missing
            if (!Schema::hasColumn('parts_request_photos', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('parts_request_photos', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parts_request_photos', function (Blueprint $table) {
            if (Schema::hasColumn('parts_request_photos', 'activity_id')) {
                $table->dropForeign(['activity_id']);
                $table->dropColumn('activity_id');
            }
            if (Schema::hasColumn('parts_request_photos', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
            if (Schema::hasColumn('parts_request_photos', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('parts_request_photos', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
        });
    }
};
