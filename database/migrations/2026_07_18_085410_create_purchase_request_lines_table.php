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
        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('preferred_supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('item_type', 20);
            $table->string('sku', 50)->nullable();
            $table->string('description');
            $table->string('uom_code', 20);
            $table->string('uom_name');
            $table->decimal('quantity', 19, 4);
            $table->decimal('estimated_unit_cost', 19, 4);
            $table->decimal('estimated_total', 19, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['purchase_request_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_lines');
    }
};
