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
        Schema::create('document_number_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_sequence_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('document_number', 150)->unique();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['document_sequence_id', 'number'], 'document_sequence_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_number_reservations');
    }
};
