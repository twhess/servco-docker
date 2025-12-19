<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_software_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('software_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('installed_version')->nullable();
            $table->date('installed_on')->nullable();
            $table->string('license_key_last4', 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('device_id');
            $table->index('software_product_id');
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_software_installations');
    }
};
