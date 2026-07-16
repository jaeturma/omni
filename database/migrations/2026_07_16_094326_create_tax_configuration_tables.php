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
        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->restrictOnDelete();
            $table->string('taxpayer_type');
            $table->string('registration_type');
            $table->string('vat_status');
            $table->string('income_tax_option');
            $table->boolean('percentage_tax_registered');
            $table->decimal('percentage_tax_rate', 9, 6)->nullable();
            $table->date('percentage_tax_effective_from')->nullable();
            $table->date('percentage_tax_effective_to')->nullable();
            $table->string('filing_frequency');
            $table->date('registration_start_date');
            $table->string('first_filing_period');
            $table->string('rdo_code', 5);
            $table->string('tin', 20);
            $table->string('branch_code', 5);
            $table->string('registered_books_type');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedTinyInteger('active_marker')->nullable();
            $table->timestamps();
            $table->unique(['business_profile_id', 'active_marker']);
        });
        Schema::create('tax_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_profile_id')->constrained()->restrictOnDelete();
            $table->string('tax_type');
            $table->decimal('rate', 9, 6);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['tax_profile_id', 'tax_type', 'effective_from', 'effective_to'], 'tax_rates_effective_period_index');
        });
        Schema::create('tax_form_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_profile_id')->constrained()->cascadeOnDelete();
            $table->string('form_code');
            $table->string('filing_frequency');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tax_profile_id', 'form_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_form_registrations');
        Schema::dropIfExists('tax_rate_settings');
        Schema::dropIfExists('tax_profiles');
    }
};
