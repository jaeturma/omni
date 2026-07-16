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
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('invoice_number', 150)->nullable()->unique();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('customer_name');
            $table->string('customer_tin', 50)->nullable();
            $table->text('billing_address');
            $table->string('customer_po_number')->nullable();
            $table->string('source_type', 20);
            $table->decimal('gross_amount', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('net_sales_amount', 19, 4)->default(0);
            $table->decimal('expected_withholding_amount', 19, 4)->default(0);
            $table->decimal('total_receivable', 19, 4)->default(0);
            $table->decimal('paid_amount', 19, 4)->default(0);
            $table->decimal('balance_due', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'status', 'due_date']);
            $table->index(['fiscal_period_id', 'invoice_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
