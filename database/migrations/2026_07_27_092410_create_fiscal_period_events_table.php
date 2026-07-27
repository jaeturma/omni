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
        Schema::create('fiscal_period_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->string('action', 20)->index();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->text('notes')->nullable();
            $table->json('checklist')->nullable();
            $table->json('overrides')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('performed_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_period_events');
    }
};
