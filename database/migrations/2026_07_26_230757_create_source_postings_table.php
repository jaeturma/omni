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
        Schema::create('source_postings', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('last_attempted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_postings');
    }
};
