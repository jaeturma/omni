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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('canvass_quotation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('purchase_order_number', 150)->nullable()->unique();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('supplier_name');
            $table->string('supplier_tin', 30)->nullable();
            $table->text('supplier_address')->nullable();
            $table->text('delivery_location');
            $table->string('supplier_quotation_reference')->nullable();
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('document_discount_rate', 9, 6)->default(0);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('line_discount_total', 19, 4)->default(0);
            $table->decimal('document_discount_amount', 19, 4)->default(0);
            $table->decimal('freight', 19, 4)->default(0);
            $table->decimal('other_charges', 19, 4)->default(0);
            $table->decimal('grand_total', 19, 4)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'order_date']);
            $table->index(['status', 'order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
