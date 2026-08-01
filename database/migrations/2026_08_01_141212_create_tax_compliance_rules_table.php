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
        Schema::create('tax_compliance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('tax_compliance_rules')->restrictOnDelete();
            $table->string('tax_type', 100);
            $table->string('bir_form_number', 30);
            $table->string('form_title');
            $table->string('taxpayer_applicability', 100);
            $table->string('registration_applicability', 30)->default('any');
            $table->string('filing_frequency', 30);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('tax_rate', 9, 6)->nullable();
            $table->text('tax_base_rule');
            $table->text('credit_rule');
            $table->text('deadline_rule');
            $table->boolean('amendment_supported')->default(false);
            $table->json('attachment_requirements')->nullable();
            $table->string('official_reference_title');
            $table->text('official_reference_url');
            $table->date('last_reviewed_on');
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->text('reviewer_notes')->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(
                ['tax_profile_id', 'bir_form_number', 'effective_from', 'effective_to'],
                'tax_rules_effective_period_index',
            );
            $table->index(['active', 'last_reviewed_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_compliance_rules');
    }
};
