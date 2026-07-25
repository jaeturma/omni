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
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('receiving_record_line_id')->nullable()->after('inventory_opening_balance_line_id')
                ->constrained(indexName: 'inventory_movement_receiving_line_fk')->restrictOnDelete();
            $table->decimal('balance_quantity_before', 19, 4)->nullable()->after('total_cost');
            $table->decimal('balance_average_cost_before', 19, 4)->nullable()->after('balance_quantity_before');
            $table->decimal('balance_quantity_after', 19, 4)->nullable()->after('balance_average_cost_before');
            $table->decimal('balance_average_cost_after', 19, 4)->nullable()->after('balance_quantity_after');
            $table->unique('receiving_record_line_id', 'inventory_movement_receiving_line_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropUnique('inventory_movement_receiving_line_unique');
            $table->dropConstrainedForeignId('receiving_record_line_id');
            $table->dropColumn(['balance_quantity_before', 'balance_average_cost_before', 'balance_quantity_after', 'balance_average_cost_after']);
        });
    }
};
