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
        Schema::create('posting_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source_type', 40)->index();
            $table->foreignId('debit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('credit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('expense_category', 50)->nullable();
            $table->string('customer_type', 50)->nullable();
            $table->string('supplier_type', 50)->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('tax_code', 40)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['source_type', 'is_active', 'effective_from', 'effective_to'], 'posting_rules_resolution_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posting_rules');
    }
};
