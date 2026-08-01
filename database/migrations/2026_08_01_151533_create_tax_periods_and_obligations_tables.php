<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_profile_id')->constrained()->restrictOnDelete();
            $table->string('frequency', 30);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('capture_start');
            $table->unsignedSmallInteger('tax_year');
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->string('label', 80);
            $table->timestamps();
            $table->unique(['tax_profile_id', 'frequency', 'period_start', 'period_end'], 'tax_periods_unique_period');
        });

        Schema::create('tax_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_compliance_rule_id')->constrained()->restrictOnDelete();
            $table->string('tax_type', 100);
            $table->string('bir_form_number', 30);
            $table->date('original_due_date');
            $table->date('adjusted_due_date')->nullable();
            $table->text('deadline_rule_source');
            $table->string('status', 30)->default('upcoming')->index();
            $table->string('filing_status', 30)->default('not_filed');
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('amendment_status', 30)->default('original');
            $table->foreignId('assigned_reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->json('rule_snapshot');
            $table->timestamps();
            $table->unique(['tax_period_id', 'bir_form_number']);
            $table->index(['original_due_date', 'adjusted_due_date']);
        });

        Schema::create('tax_obligation_deadline_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_obligation_id')->constrained()->restrictOnDelete();
            $table->date('previous_due_date');
            $table->date('adjusted_due_date');
            $table->text('reason');
            $table->string('source_title');
            $table->text('source_url');
            $table->foreignId('adjusted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_obligation_deadline_adjustments');
        Schema::dropIfExists('tax_obligations');
        Schema::dropIfExists('tax_periods');
    }
};
