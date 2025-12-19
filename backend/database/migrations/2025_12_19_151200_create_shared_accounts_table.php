<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username');
            $table->text('password_encrypted');
            $table->string('url')->nullable();
            $table->text('mfa_notes')->nullable();
            $table->foreignId('owner_location_id')->nullable()->constrained('service_locations')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('owner_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_accounts');
    }
};
