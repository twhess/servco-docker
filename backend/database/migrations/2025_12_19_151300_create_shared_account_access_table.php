<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_account_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('access_level', ['view', 'edit', 'admin'])->default('view');
            $table->foreignId('granted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->unique(['shared_account_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_account_access');
    }
};
