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
        Schema::create('inventory_adjustment_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()->constrained(indexName: 'inventory_adjustment_number_reservation_fk')->restrictOnDelete();
            $table->string('adjustment_number', 150)->nullable()->unique();
            $table->date('adjustment_date');
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->foreignId('inventory_adjustment_reason_id')->constrained(indexName: 'inventory_adjustment_reason_fk')->restrictOnDelete();
            $table->text('explanation');
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['warehouse_id', 'status', 'adjustment_date'], 'inventory_adjustment_warehouse_status_date_index');
        });
        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained(indexName: 'inventory_adjustment_line_header_fk')->restrictOnDelete();
            $table->foreignId('product_service_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->decimal('total_cost', 19, 4)->nullable();
            $table->timestamps();
            $table->unique(['inventory_adjustment_id', 'product_service_id'], 'inventory_adjustment_line_product_unique');
        });
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('inventory_adjustment_line_id')->nullable()->after('delivery_line_id')
                ->constrained(indexName: 'inventory_movement_adjustment_line_fk')->restrictOnDelete();
            $table->index('inventory_adjustment_line_id', 'inventory_movement_adjustment_line_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_adjustment_line_id');
        });
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_adjustment_reasons');
    }
};
