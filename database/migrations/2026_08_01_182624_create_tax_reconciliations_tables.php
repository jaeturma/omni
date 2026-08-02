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
        Schema::create('tax_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_obligation_id')->unique()->constrained()->restrictOnDelete();
            $table->string('tax_base_rule');
            foreach (['gross_sales', 'credit_adjustments', 'operational_net_sales', 'receipt_basis', 'ledger_revenue', 'customer_withholding', 'approved_adjustments', 'difference'] as $column) {
                $table->decimal($column, 19, 4)->default(0);
            }
            $table->unsignedInteger('critical_difference_count')->default(0);
            $table->json('parameters');
            $table->json('source_snapshot');
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('tax_reconciliation_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_reconciliation_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->text('reason');
            $table->string('evidence_reference');
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['tax_reconciliation_id', 'status'], 'tax_recon_adjustment_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_reconciliation_adjustments');
        Schema::dropIfExists('tax_reconciliations');
    }
};
