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
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('record_type')->unique();
            $table->string('classification', 30)->index();
            $table->unsignedSmallInteger('retention_months')->nullable();
            $table->string('retention_trigger', 80);
            $table->string('disposition', 40);
            $table->text('legal_basis');
            $table->boolean('active')->default(true)->index();
            $table->date('reviewed_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('record_archives', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('archived_at');
            $table->foreignId('archived_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_archives');
        Schema::dropIfExists('retention_policies');
    }
};
