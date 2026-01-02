<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('label')->nullable();
            $table->string('company_name')->nullable();
            $table->string('attention')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state', 2);
            $table->string('postal_code', 10);
            $table->string('country', 2)->default('US');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('instructions')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_validated')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['city', 'state']);
            $table->index('postal_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
