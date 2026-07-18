<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('receiving_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_number_reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('internal_number', 50)->nullable()->unique();
            $table->string('supplier_invoice_number', 100);
            $table->date('invoice_date')->index();
            $table->date('due_date')->index();
            $table->string('supplier_name');
            $table->string('supplier_tin', 30)->nullable();
            $table->text('supplier_address')->nullable();
            $table->decimal('gross_purchase_amount', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('net_purchase_amount', 19, 4)->default(0);
            $table->decimal('freight_amount', 19, 4)->default(0);
            $table->decimal('other_charges_amount', 19, 4)->default(0);
            $table->decimal('withholding_expected_amount', 19, 4)->default(0);
            $table->decimal('total_payable', 19, 4)->default(0);
            $table->decimal('paid_amount', 19, 4)->default(0);
            $table->decimal('balance_due', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_invoice_number'], 'supplier_invoice_number_unique');
            $table->index(['supplier_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
