<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->date('statement_start_date');
            $table->date('statement_end_date');
            $table->string('source_filename');
            $table->char('file_hash', 64);
            $table->json('column_mapping');
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('imported_at');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['financial_account_id', 'file_hash']);
            $table->index(['financial_account_id', 'statement_start_date', 'statement_end_date'], 'bank_statement_import_account_period_index');
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->date('transaction_date');
            $table->date('posting_date');
            $table->text('description');
            $table->string('reference_number')->nullable();
            $table->decimal('debit', 19, 4)->default(0);
            $table->decimal('credit', 19, 4)->default(0);
            $table->decimal('running_balance', 19, 4)->nullable();
            $table->decimal('normalized_amount', 19, 4);
            $table->string('match_status')->default('unreconciled');
            $table->string('matched_transaction_reference')->nullable();
            $table->json('original_values');
            $table->timestamps();
            $table->unique(['bank_statement_import_id', 'line_number'], 'bank_statement_import_line_unique');
            $table->index(['bank_statement_import_id', 'match_status'], 'bank_statement_import_match_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
    }
};
