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
        Schema::table('tax_compliance_rules', function (Blueprint $table): void {
            $table->json('calculation_parameters')->nullable()->after('credit_rule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_compliance_rules', function (Blueprint $table) {
            $table->dropColumn('calculation_parameters');
        });
    }
};
