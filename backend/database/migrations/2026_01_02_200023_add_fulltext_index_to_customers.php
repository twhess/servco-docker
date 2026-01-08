<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add fulltext index for faster searching
        DB::statement('ALTER TABLE customers ADD FULLTEXT INDEX customers_search_fulltext (company_name, formatted_name, normalized_name, dot_number)');

        // Add a standard index on formatted_name for LIKE queries
        Schema::table('customers', function (Blueprint $table) {
            $table->index('formatted_name', 'customers_formatted_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_search_fulltext');
            $table->dropIndex('customers_formatted_name_index');
        });
    }
};
