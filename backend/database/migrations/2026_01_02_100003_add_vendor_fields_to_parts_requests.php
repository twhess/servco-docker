<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts_requests', function (Blueprint $table) {
            // Add vendor_id after vendor_name
            $table->foreignId('vendor_id')
                ->nullable()
                ->after('vendor_name')
                ->constrained()
                ->nullOnDelete();

            // Add vendor_address_id after vendor_id
            $table->foreignId('vendor_address_id')
                ->nullable()
                ->after('vendor_id')
                ->constrained('addresses')
                ->nullOnDelete();

            // Add indexes
            $table->index('vendor_id');
            $table->index('vendor_address_id');
        });
    }

    public function down(): void
    {
        Schema::table('parts_requests', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['vendor_address_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropIndex(['vendor_address_id']);
            $table->dropColumn(['vendor_id', 'vendor_address_id']);
        });
    }
};
