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
        Schema::create('tax_filings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_obligation_id')->constrained()->restrictOnDelete();
            $table->foreignId('bir2551q_worksheet_id')->nullable()->unique()->constrained('bir2551q_worksheets')->restrictOnDelete();
            $table->foreignId('bir1701q_worksheet_id')->nullable()->unique()->constrained('bir1701q_worksheets')->restrictOnDelete();
            $table->foreignId('original_filing_id')->nullable()->constrained('tax_filings')->restrictOnDelete();
            $table->string('bir_form_number', 20);
            $table->unsignedInteger('worksheet_revision');
            $table->string('filing_channel', 80);
            $table->date('filing_date');
            $table->string('return_reference')->unique();
            $table->boolean('is_amended')->default(false);
            $table->text('amendment_reason')->nullable();
            $table->decimal('worksheet_amount_payable', 19, 4);
            $table->decimal('amount_declared', 19, 4);
            $table->decimal('declared_difference', 19, 4)->default(0);
            $table->timestamp('confirmed_at');
            $table->foreignId('filed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tax_obligation_id', 'filing_date']);
        });

        Schema::create('tax_filing_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_filing_id')->constrained()->restrictOnDelete();
            $table->string('payment_channel', 80);
            $table->date('payment_date');
            $table->string('payment_reference')->unique();
            $table->decimal('amount_paid', 19, 4);
            $table->string('bank_or_provider')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['tax_filing_id', 'payment_date']);
        });

        Schema::create('tax_filing_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_filing_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_filing_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('attachment_type', 40);
            $table->string('original_filename');
            $table->string('stored_filename')->unique();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('file_size');
            $table->char('file_hash', 64);
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['tax_filing_id', 'attachment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_filing_attachments');
        Schema::dropIfExists('tax_filing_payments');
        Schema::dropIfExists('tax_filings');
    }
};
