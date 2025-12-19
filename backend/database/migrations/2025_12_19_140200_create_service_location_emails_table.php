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
        Schema::create('service_location_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_location_id')->constrained()->cascadeOnDelete();
            $table->string('label')->comment('e.g., General, Parts Orders, Billing');
            $table->string('email');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_public')->default(true)->comment('Visible to customers');
            $table->timestamps();

            // Indexes
            $table->index('service_location_id');
            $table->index('is_primary');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_location_emails');
    }
};
