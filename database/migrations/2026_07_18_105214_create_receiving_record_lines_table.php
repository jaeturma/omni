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
        Schema::create('receiving_record_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('item_type', 20);
            $table->string('sku', 50)->nullable();
            $table->string('description');
            $table->string('uom_code', 20);
            $table->string('uom_name');
            $table->decimal('received_quantity', 19, 4);
            $table->decimal('accepted_quantity', 19, 4)->default(0);
            $table->decimal('rejected_quantity', 19, 4)->default(0);
            $table->decimal('credited_quantity', 19, 4)->default(0);
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['receiving_record_id', 'line_number']);
            $table->unique(['receiving_record_id', 'purchase_order_line_id'], 'receiving_lines_record_po_line_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_record_lines');
    }
};
