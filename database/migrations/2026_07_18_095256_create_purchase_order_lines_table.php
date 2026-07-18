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
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('item_type', 20);
            $table->string('sku', 50)->nullable();
            $table->string('description');
            $table->string('uom_code', 20);
            $table->string('uom_name');
            $table->decimal('ordered_quantity', 19, 4);
            $table->decimal('received_quantity', 19, 4)->default(0);
            $table->decimal('billed_quantity', 19, 4)->default(0);
            $table->decimal('cancelled_quantity', 19, 4)->default(0);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('discount_rate', 9, 6)->default(0);
            $table->decimal('gross_amount', 19, 4);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('net_amount', 19, 4);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
