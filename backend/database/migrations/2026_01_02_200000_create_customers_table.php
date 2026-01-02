<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Identity
            $table->string('fb_id')->nullable()->unique();
            $table->string('external_id')->nullable();
            $table->enum('source', ['manual', 'import'])->default('manual');

            // Names (parsed from Company Name)
            $table->string('company_name');
            $table->string('formatted_name');
            $table->string('detail')->nullable();
            $table->string('normalized_name');

            // Contact
            $table->string('phone')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();

            // Business Info
            $table->string('dot_number')->nullable();
            $table->string('customer_group')->nullable();
            $table->string('assigned_shop')->nullable();
            $table->json('associated_shops')->nullable();
            $table->string('sales_rep')->nullable();

            // Credit & Billing
            $table->string('credit_terms')->nullable();
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->string('tax_location')->nullable();
            $table->string('price_level')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->string('tax_exempt_number')->nullable();
            $table->string('discount')->nullable();
            $table->string('default_labor_rate')->nullable();

            // PO Settings
            $table->boolean('po_required_create_so')->default(false);
            $table->boolean('po_required_create_invoice')->default(false);
            $table->string('blanket_po_number')->nullable();

            // Portal Settings
            $table->boolean('portal_enabled')->default(false);
            $table->string('portal_code')->nullable();
            $table->boolean('portal_can_see_invoices')->default(false);
            $table->boolean('portal_can_pay_invoices')->default(false);

            // Misc Settings (JSON for flexibility)
            $table->json('settings')->nullable();

            // Status & Notes
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('external_created_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index('normalized_name');
            $table->index('dot_number');
            $table->index('source');
            $table->index('is_active');
            $table->index('customer_group');
            $table->index('assigned_shop');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
