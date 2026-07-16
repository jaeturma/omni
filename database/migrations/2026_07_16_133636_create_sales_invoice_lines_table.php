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
        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('delivery_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('item_type', 20);
            $table->string('sku', 50)->nullable();
            $table->string('description');
            $table->string('uom_code', 20);
            $table->string('uom_name');
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('discount_rate', 9, 6)->default(0);
            $table->decimal('gross_amount', 19, 4);
            $table->decimal('discount_amount', 19, 4);
            $table->decimal('net_amount', 19, 4);
            $table->timestamps();
            $table->unique(['sales_invoice_id', 'line_number']);
            $table->index(['sales_order_line_id', 'delivery_line_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
    }
};
