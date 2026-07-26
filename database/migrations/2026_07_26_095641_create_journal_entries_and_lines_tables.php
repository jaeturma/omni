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
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('journal_number', 40)->unique();
            $table->date('journal_date')->index();
            $table->date('document_date');
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->string('journal_type', 30);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('description');
            $table->decimal('total_debit', 19, 4)->default(0);
            $table->decimal('total_credit', 19, 4)->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reversal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->text('description')->nullable();
            $table->decimal('debit', 19, 4)->default(0);
            $table->decimal('credit', 19, 4)->default(0);
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('product_services')->restrictOnDelete();
            $table->string('source_line_type', 40)->nullable();
            $table->unsignedBigInteger('source_line_id')->nullable();
            $table->timestamps();
            $table->unique(['journal_entry_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
