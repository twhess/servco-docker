<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'physical' to the address_type enum for customer addresses
        DB::statement("ALTER TABLE addressables MODIFY COLUMN address_type ENUM('pickup', 'billing', 'shipping', 'physical', 'other') DEFAULT 'pickup'");
    }

    public function down(): void
    {
        // Revert to original enum values
        // First update any 'physical' types to 'other' to avoid data loss
        DB::statement("UPDATE addressables SET address_type = 'other' WHERE address_type = 'physical'");
        DB::statement("ALTER TABLE addressables MODIFY COLUMN address_type ENUM('pickup', 'billing', 'shipping', 'other') DEFAULT 'pickup'");
    }
};
