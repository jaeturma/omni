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
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('fiscal_year_scope')->default(0);
            $table->string('document_type', 50);
            $table->string('prefix', 50)->default('');
            $table->string('suffix', 50)->default('');
            $table->unsignedBigInteger('current_number')->default(0);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->string('reset_rule', 20)->default('never');
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['business_profile_id', 'document_type', 'fiscal_year_scope'], 'document_sequence_scope_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
