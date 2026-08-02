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
        Schema::create('production_cutovers', function (Blueprint $table) {
            $table->id();
            $table->date('cutover_date')->unique();
            $table->string('status')->default('draft')->index();
            $table->string('legacy_freeze_reference');
            $table->string('source_documents_reference');
            $table->foreignId('backup_run_id')->constrained()->restrictOnDelete();
            $table->string('rollback_rehearsal_reference');
            $table->boolean('cash_confirmed')->default(false);
            $table->boolean('owner_equity_confirmed')->default(false);
            $table->boolean('sequence_confirmed')->default(false);
            $table->boolean('tax_control_confirmed')->default(false);
            $table->json('report_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_cutovers');
    }
};
