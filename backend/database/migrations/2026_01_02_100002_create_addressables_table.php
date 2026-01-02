<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addressables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('address_id')->constrained()->cascadeOnDelete();
            $table->morphs('addressable'); // Creates addressable_type, addressable_id with index
            $table->enum('address_type', ['pickup', 'billing', 'shipping', 'other'])->default('pickup');
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['address_id', 'addressable_type', 'addressable_id'], 'addressables_unique');
            // morphs() already creates the composite index, so no need for additional index
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addressables');
    }
};
