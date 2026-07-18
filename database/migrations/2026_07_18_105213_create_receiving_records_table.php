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
        Schema::create('receiving_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('receiving_number', 150)->nullable()->unique();
            $table->date('receiving_date');
            $table->string('supplier_name');
            $table->text('delivery_location');
            $table->string('delivery_receipt_number')->nullable();
            $table->string('supplier_invoice_reference')->nullable();
            $table->string('inspection_reference')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('purchase_order_status_before_receipt', 30)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['purchase_order_id', 'receiving_date']);
            $table->index(['status', 'receiving_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_records');
    }
};
