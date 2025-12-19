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
        // Tables that need audit fields (excluding pivot tables and system tables)
        $tables = [
            'users',
            'service_locations',
            'service_location_phones',
            'service_location_emails',
            'location_positions',
            'items',
            'item_movements',
            'location_areas',
            'device_types',
            'devices',
            'device_assignments',
            'issue_categories',
            'device_issues',
            'repair_events',
            'parts_catalog',
            'repair_parts_used',
            'software_products',
            'subscriptions',
            'device_software_installations',
            'shared_accounts',
            'shared_account_access',
            'shared_account_device_links',
            'qr_tokens',
            'qr_scans',
            'found_device_contacts',
            'tool_categories',
            'tools',
            'tool_transfers',
            'parts_request_types',
            'parts_request_statuses',
            'urgency_levels',
            'parts_requests',
            'parts_request_events',
            'parts_request_photos',
            'parts_request_locations',
            'geo_fences',
            'geo_fence_events',
            'roles',
            'permissions',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    // Only add if columns don't exist
                    if (!Schema::hasColumn($table->getTable(), 'created_by')) {
                        $table->unsignedBigInteger('created_by')->nullable()->after('id');
                        $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                    }

                    if (!Schema::hasColumn($table->getTable(), 'updated_by')) {
                        $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                        $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'service_locations',
            'service_location_phones',
            'service_location_emails',
            'location_positions',
            'items',
            'item_movements',
            'location_areas',
            'device_types',
            'devices',
            'device_assignments',
            'issue_categories',
            'device_issues',
            'repair_events',
            'parts_catalog',
            'repair_parts_used',
            'software_products',
            'subscriptions',
            'device_software_installations',
            'shared_accounts',
            'shared_account_access',
            'shared_account_device_links',
            'qr_tokens',
            'qr_scans',
            'found_device_contacts',
            'tool_categories',
            'tools',
            'tool_transfers',
            'parts_request_types',
            'parts_request_statuses',
            'urgency_levels',
            'parts_requests',
            'parts_request_events',
            'parts_request_photos',
            'parts_request_locations',
            'geo_fences',
            'geo_fence_events',
            'roles',
            'permissions',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (Schema::hasColumn($table->getTable(), 'created_by')) {
                        $table->dropForeign([$table->getTable() . '_created_by_foreign']);
                        $table->dropColumn('created_by');
                    }

                    if (Schema::hasColumn($table->getTable(), 'updated_by')) {
                        $table->dropForeign([$table->getTable() . '_updated_by_foreign']);
                        $table->dropColumn('updated_by');
                    }
                });
            }
        }
    }
};
