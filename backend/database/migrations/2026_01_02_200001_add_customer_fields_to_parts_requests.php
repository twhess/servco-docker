<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts_requests', function (Blueprint $table) {
            // Add customer_id after customer_address (the old text field)
            $table->foreignId('customer_id')
                ->nullable()
                ->after('customer_address')
                ->constrained()
                ->nullOnDelete();

            // Add customer_address_id after customer_id
            $table->foreignId('customer_address_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('addresses')
                ->nullOnDelete();

            // Add indexes
            $table->index('customer_id');
            $table->index('customer_address_id');
        });
    }

    public function down(): void
    {
        Schema::table('parts_requests', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['customer_address_id']);
            $table->dropIndex(['parts_requests_customer_id_index']);
            $table->dropIndex(['parts_requests_customer_address_id_index']);
            $table->dropColumn(['customer_id', 'customer_address_id']);
        });
    }
};
