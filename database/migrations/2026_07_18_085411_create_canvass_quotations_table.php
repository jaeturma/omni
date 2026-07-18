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
        Schema::create('canvass_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_name');
            $table->string('supplier_tin', 30)->nullable();
            $table->text('supplier_address')->nullable();
            $table->decimal('quoted_amount', 19, 4);
            $table->date('quotation_date');
            $table->date('validity_date')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('payment_terms')->nullable();
            $table->boolean('selected')->default(false);
            $table->text('evaluation_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['purchase_request_id', 'supplier_id']);
            $table->index(['purchase_request_id', 'selected']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canvass_quotations');
    }
};
