<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('issue_categories')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed', 'wont_fix'])->default('open');
            $table->timestamp('opened_on');
            $table->timestamp('closed_on')->nullable();
            $table->timestamps();

            $table->index('device_id');
            $table->index('reported_by_user_id');
            $table->index('status');
            $table->index('priority');
            $table->index('opened_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_issues');
    }
};
