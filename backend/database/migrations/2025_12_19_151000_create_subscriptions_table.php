<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_product_id')->constrained()->cascadeOnDelete();
            $table->string('plan_name')->nullable();
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'annually', 'one_time'])->default('annually');
            $table->decimal('cost_amount', 10, 2);
            $table->string('cost_currency', 3)->default('USD');
            $table->date('start_date');
            $table->date('renew_date')->nullable();
            $table->integer('seats_purchased')->nullable();
            $table->enum('status', ['active', 'trial', 'expired', 'canceled'])->default('active');
            $table->foreignId('billing_contact_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vendor_account_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('software_product_id');
            $table->index('status');
            $table->index('renew_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
