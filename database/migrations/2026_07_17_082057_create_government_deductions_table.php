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
        Schema::create('government_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('tax_rate_setting_id')->constrained()->restrictOnDelete();
            $table->string('deduction_type', 50);
            $table->string('certificate_type', 20);
            $table->string('certificate_number')->nullable();
            $table->date('certificate_date')->nullable();
            $table->date('covered_from');
            $table->date('covered_to');
            $table->decimal('gross_basis', 19, 4);
            $table->decimal('rate', 9, 6);
            $table->decimal('amount', 19, 4);
            $table->string('status', 20)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->string('attachment_reference')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'status', 'covered_to']);
            $table->index(['sales_invoice_id', 'deduction_type', 'covered_from', 'covered_to'], 'government_deductions_duplicate_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('government_deductions');
    }
};
