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
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->date('statement_start_date');
            $table->date('statement_end_date');
            $table->decimal('statement_opening_balance', 19, 4);
            $table->decimal('statement_closing_balance', 19, 4);
            $table->decimal('system_opening_balance', 19, 4);
            $table->decimal('system_closing_balance', 19, 4);
            $table->decimal('unmatched_deposits', 19, 4)->default(0);
            $table->decimal('unmatched_withdrawals', 19, 4)->default(0);
            $table->decimal('bank_charges', 19, 4)->default(0);
            $table->decimal('interest_other_items', 19, 4)->default(0);
            $table->decimal('reconciliation_difference', 19, 4);
            $table->string('status', 30)->default('draft')->index();
            $table->text('exception_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
            $table->index(['financial_account_id', 'statement_end_date'], 'bank_reconciliation_account_end_date_index');
        });

        Schema::create('bank_reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_statement_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('matched_amount', 19, 4);
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();
            $table->unique(['bank_reconciliation_id', 'bank_statement_line_id', 'cash_transaction_id'], 'bank_reconciliation_match_unique');
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('bank_reconciliation_id')->nullable()->after('petty_cash_replenishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()->after('bank_reconciliation_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_kind', 30)->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_reconciliation_id');
            $table->dropConstrainedForeignId('document_number_reservation_id');
            $table->dropColumn('adjustment_kind');
        });
        Schema::dropIfExists('bank_reconciliation_matches');
        Schema::dropIfExists('bank_reconciliations');
    }
};
