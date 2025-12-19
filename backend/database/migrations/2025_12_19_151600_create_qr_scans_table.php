<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_token_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scanned_on');
            $table->string('ip', 45)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index('qr_token_id');
            $table->index('scanned_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scans');
    }
};
