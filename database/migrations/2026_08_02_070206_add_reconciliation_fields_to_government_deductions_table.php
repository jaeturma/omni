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
        Schema::table('government_deductions', function (Blueprint $table): void {
            $table->index('sales_invoice_id', 'government_deductions_sales_invoice_fk_index');
        });

        Schema::table('government_deductions', function (Blueprint $table): void {
            $table->dropIndex('government_deductions_duplicate_index');
            $table->foreignId('journal_entry_line_id')->nullable()->after('customer_payment_id')->constrained()->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('verified_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->restrictOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by');
            $table->unique(['certificate_type', 'certificate_number'], 'government_certificate_number_unique');
            $table->unique(['sales_invoice_id', 'deduction_type', 'covered_from', 'covered_to'], 'government_deduction_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('government_deductions', function (Blueprint $table): void {
            $table->dropUnique('government_certificate_number_unique');
            $table->dropUnique('government_deduction_source_unique');
            $table->index(['sales_invoice_id', 'deduction_type', 'covered_from', 'covered_to'], 'government_deductions_duplicate_index');
            $table->dropConstrainedForeignId('journal_entry_line_id');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });

        Schema::table('government_deductions', function (Blueprint $table): void {
            $table->dropIndex('government_deductions_sales_invoice_fk_index');
        });
    }
};
