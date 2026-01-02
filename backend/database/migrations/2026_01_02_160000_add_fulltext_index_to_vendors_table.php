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
        // MySQL fulltext works well for natural language searches
        DB::statement('ALTER TABLE vendors ADD FULLTEXT INDEX vendors_search_fulltext (name, legal_name, normalized_name)');

        // Add a standard index on name column for LIKE queries that start with text (no leading wildcard)
        Schema::table('vendors', function (Blueprint $table) {
            $table->index('name', 'vendors_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex('vendors_search_fulltext');
            $table->dropIndex('vendors_name_index');
        });
    }
};
