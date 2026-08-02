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
        Schema::create('bir1701q_worksheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_obligation_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_revision_id')->nullable()->constrained('bir1701q_worksheets')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('return_type', 20)->default('original');
            $table->unsignedSmallInteger('taxable_year');
            $table->unsignedTinyInteger('quarter');
            $table->string('income_tax_method', 100);
            $table->string('deduction_method', 30);
            $table->string('status', 30)->default('draft')->index();
            $table->string('filing_status', 30)->default('not_filed');
            $table->string('review_status', 30)->default('draft');
            foreach (['cumulative_gross_sales', 'sales_returns_discounts', 'net_sales', 'cost_of_sales', 'other_income', 'gross_income', 'financial_itemized_deductions', 'osd_deduction', 'manual_deduction_adjustment', 'taxable_income_adjustment', 'taxable_income', 'income_tax_due', 'prior_quarter_taxable_income', 'prior_quarter_income_tax_due', 'prior_quarter_payments', 'verified_creditable_withholding', 'manual_creditable_withholding', 'other_allowable_credits', 'surcharge', 'interest', 'compromise_penalty', 'total_amount_payable'] as $column) {
                $table->decimal($column, 19, 4)->default(0);
            }
            $table->json('taxpayer_snapshot');
            $table->json('rule_snapshot');
            $table->json('financial_report_snapshot');
            $table->json('withholding_snapshot');
            $table->json('prior_quarter_snapshot')->nullable();
            $table->text('manual_adjustment_reason')->nullable();
            $table->string('manual_adjustment_evidence')->nullable();
            $table->string('prior_payment_evidence')->nullable();
            $table->string('withholding_evidence')->nullable();
            $table->text('other_credits_authority')->nullable();
            $table->string('other_credits_evidence')->nullable();
            $table->text('penalty_authority')->nullable();
            $table->string('penalty_evidence')->nullable();
            $table->text('preparation_notes')->nullable();
            $table->text('revision_reason')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('frozen_at')->nullable();
            $table->timestamps();
            $table->unique(['tax_obligation_id', 'revision_number']);
            $table->index(['taxable_year', 'quarter', 'return_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bir1701q_worksheets');
    }
};
