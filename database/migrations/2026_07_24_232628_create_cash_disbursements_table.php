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
        Schema::create('cash_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('disbursement_number', 150)->nullable()->unique();
            $table->date('disbursement_date');
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 40)->index();
            $table->foreignId('supplier_payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('payee');
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('reference_number')->nullable();
            $table->decimal('gross_settlement', 19, 4);
            $table->decimal('deductions_or_bank_charges', 19, 4)->default(0);
            $table->decimal('net_cash_out', 19, 4);
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('release_date')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('clearing_date')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('stopped_at')->nullable();
            $table->foreignId('stopped_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('stop_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['financial_account_id', 'status', 'disbursement_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_disbursements');
    }
};
