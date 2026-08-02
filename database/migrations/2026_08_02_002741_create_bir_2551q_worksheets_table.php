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
        Schema::create('bir2551q_worksheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_obligation_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_reconciliation_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_revision_id')->nullable()->constrained('bir2551q_worksheets')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('return_type', 20)->default('original');
            $table->string('basis_type', 30);
            $table->unsignedSmallInteger('return_year');
            $table->unsignedTinyInteger('quarter');
            $table->string('status', 30)->default('draft')->index();
            $table->string('filing_status', 30)->default('not_filed');
            $table->string('review_status', 30)->default('draft');
            foreach (['gross_taxable_amount', 'excluded_amount', 'taxable_amount', 'gross_tax_due', 'allowable_credits', 'government_tax_withheld', 'prior_payment', 'surcharge', 'interest', 'compromise_penalty', 'total_amount_payable'] as $column) {
                $table->decimal($column, 19, 4)->default(0);
            }
            $table->decimal('tax_rate', 9, 6);
            $table->json('taxpayer_snapshot');
            $table->json('rule_snapshot');
            $table->json('reconciliation_snapshot');
            $table->json('source_snapshot');
            $table->json('excluded_source_keys')->nullable();
            $table->text('exclusion_reason')->nullable();
            $table->string('exclusion_evidence')->nullable();
            $table->text('credits_authority')->nullable();
            $table->string('credits_evidence')->nullable();
            $table->string('prior_payment_reference')->nullable();
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
            $table->index(['return_year', 'quarter', 'return_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bir2551q_worksheets');
    }
};
