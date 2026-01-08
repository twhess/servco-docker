<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('fb_id')->nullable();
            $table->json('raw_data');
            $table->enum('action', ['created', 'updated', 'skipped', 'merge_needed', 'error']);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->index(['customer_import_id', 'action']);
            $table->index('fb_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_import_rows');
    }
};
