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
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->foreignId('reverses_entry_id')->nullable()->after('reversal_entry_id')
                ->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('correction_of_id')->nullable()->after('reverses_entry_id')
                ->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->text('reversal_reason')->nullable()->after('correction_of_id');
            $table->date('auto_reverse_on')->nullable()->after('reversal_reason');
            $table->boolean('is_auto_reversal')->default(false)->after('auto_reverse_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('correction_of_id');
            $table->dropConstrainedForeignId('reverses_entry_id');
            $table->dropColumn(['reversal_reason', 'auto_reverse_on', 'is_auto_reversal']);
        });
    }
};
