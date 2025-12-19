<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('part_number')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->timestamps();

            $table->index('part_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_catalog');
    }
};
