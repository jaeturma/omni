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
        Schema::table('tax_compliance_rules', function (Blueprint $table) {
            $table->json('applicable_quarters')->nullable()->after('filing_frequency');
            $table->unsignedTinyInteger('deadline_months_after_period_end')->nullable()->after('deadline_rule');
            $table->unsignedTinyInteger('deadline_day')->nullable()->after('deadline_months_after_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_compliance_rules', function (Blueprint $table) {
            $table->dropColumn(['applicable_quarters', 'deadline_months_after_period_end', 'deadline_day']);
        });
    }
};
