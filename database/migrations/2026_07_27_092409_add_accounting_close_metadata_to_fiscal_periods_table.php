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
        Schema::table('fiscal_periods', function (Blueprint $table): void {
            $table->text('close_notes')->nullable()->after('closed_by');
            $table->json('close_checklist')->nullable()->after('close_notes');
            $table->json('close_overrides')->nullable()->after('close_checklist');
            $table->text('lock_notes')->nullable()->after('locked_by');
            $table->timestamp('reopened_at')->nullable()->after('lock_notes');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->restrictOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by');
            $table->unsignedInteger('lock_version')->default(0)->after('reopen_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn([
                'close_notes', 'close_checklist', 'close_overrides', 'lock_notes',
                'reopened_at', 'reopen_reason', 'lock_version',
            ]);
        });
    }
};
