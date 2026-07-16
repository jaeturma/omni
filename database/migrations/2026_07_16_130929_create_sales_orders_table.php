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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('sales_order_number', 150)->nullable()->unique();
            $table->date('order_date');
            $table->date('promised_delivery_date')->nullable();
            $table->string('customer_po_number')->nullable();
            $table->unsignedSmallInteger('payment_terms')->default(0);
            $table->string('customer_name');
            $table->string('customer_tin', 30)->nullable();
            $table->text('billing_address');
            $table->text('delivery_address');
            $table->text('notes')->nullable();
            $table->decimal('document_discount_rate', 9, 6)->default(0);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('line_discount_total', 19, 4)->default(0);
            $table->decimal('document_discount_amount', 19, 4)->default(0);
            $table->decimal('grand_total', 19, 4)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'order_date']);
            $table->index(['status', 'order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
