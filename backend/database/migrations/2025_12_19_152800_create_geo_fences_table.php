<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_fences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('entity_type', ['service_location', 'vendor', 'customer_address']);
            $table->unsignedBigInteger('entity_id');
            $table->decimal('center_lat', 10, 7);
            $table->decimal('center_lng', 10, 7);
            $table->integer('radius_m')->default(100);
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['center_lat', 'center_lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_fences');
    }
};
