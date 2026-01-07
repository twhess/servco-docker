<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enable PIN auth for users who already have a pin_code set.
     */
    public function up(): void
    {
        // Enable PIN for all users who have a pin_code set
        DB::table('users')
            ->whereNotNull('pin_code')
            ->where('pin_code', '!=', '')
            ->update(['pin_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't want to disable PIN for users on rollback
        // as that could lock people out
    }
};
