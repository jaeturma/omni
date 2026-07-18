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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('document_number_reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('expense_number', 50)->nullable()->unique();
            $table->date('expense_date')->index();
            $table->string('payee_name');
            $table->string('expense_category', 50)->index();
            $table->string('description');
            $table->text('business_purpose');
            $table->string('reference_number', 100)->nullable();
            $table->string('project_reference', 100)->nullable();
            $table->boolean('receipt_available')->default(false);
            $table->string('receipt_reference', 100)->nullable();
            $table->decimal('gross_amount', 19, 4);
            $table->decimal('withholding_amount', 19, 4)->default(0);
            $table->decimal('other_deductions', 19, 4)->default(0);
            $table->decimal('net_cash_paid', 19, 4)->default(0);
            $table->boolean('reimbursable')->default(false)->index();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['fiscal_period_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
