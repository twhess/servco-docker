<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('found_device_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('found_device_contacts');
    }
};
