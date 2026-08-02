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
        Schema::create('withholding_certificate_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('government_deduction_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_obligation_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->string('evidence_reference');
            $table->text('notes')->nullable();
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('applied_at');
            $table->timestamps();
            $table->unique(['government_deduction_id', 'tax_obligation_id'], 'withholding_application_unique');
            $table->index(['tax_obligation_id', 'applied_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withholding_certificate_applications');
    }
};
