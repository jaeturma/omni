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
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('registered_business_name');
            $table->string('trade_name');
            $table->string('proprietor_name');
            $table->string('tin', 20);
            $table->string('branch_code', 5);
            $table->string('rdo_code', 5);
            $table->date('registration_date');
            $table->date('business_start_date');
            $table->text('registered_address');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->char('default_currency', 3)->default('PHP');
            $table->string('timezone')->default('Asia/Manila');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->string('logo_path')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedTinyInteger('active_marker')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
