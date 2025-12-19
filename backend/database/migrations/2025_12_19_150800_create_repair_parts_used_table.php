<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_parts_used', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts_catalog')->restrictOnDelete();
            $table->integer('qty')->default(1);
            $table->decimal('unit_cost_at_time', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('repair_event_id');
            $table->index('part_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_parts_used');
    }
};
