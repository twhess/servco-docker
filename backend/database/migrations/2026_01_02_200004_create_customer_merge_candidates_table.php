<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_merge_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_row_id')->constrained('customer_import_rows')->cascadeOnDelete();
            $table->foreignId('matched_customer_id')->constrained('customers');

            $table->json('incoming_data');
            $table->decimal('match_score', 5, 2);
            $table->json('match_reasons');

            $table->enum('status', ['pending', 'merged', 'created_new', 'skipped'])->default('pending');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('resolution_details')->nullable();

            $table->timestamps();

            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_merge_candidates');
    }
};
