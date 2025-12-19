<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_issue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vendor_name')->nullable();
            $table->decimal('labor_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_on');
            $table->timestamp('completed_on')->nullable();
            $table->timestamps();

            $table->index('device_issue_id');
            $table->index('device_id');
            $table->index('performed_by_user_id');
            $table->index('started_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_events');
    }
};
