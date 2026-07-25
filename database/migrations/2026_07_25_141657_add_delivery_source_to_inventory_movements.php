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
            $table->foreignId('delivery_line_id')->nullable()->after('receiving_record_line_id')
                ->constrained(indexName: 'inventory_movement_delivery_line_fk')->restrictOnDelete();
            $table->unique('delivery_line_id', 'inventory_movement_delivery_line_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropUnique('inventory_movement_delivery_line_unique');
            $table->dropConstrainedForeignId('delivery_line_id');
        });
    }
};
