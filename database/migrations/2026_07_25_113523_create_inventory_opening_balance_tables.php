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
        Schema::create('inventory_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()
                ->constrained(indexName: 'inventory_opening_number_reservation_fk')->restrictOnDelete();
            $table->string('batch_number', 150)->nullable()->unique();
            $table->date('opening_date');
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['warehouse_id', 'status', 'opening_date'], 'inventory_opening_warehouse_status_date_index');
        });

        Schema::create('inventory_opening_balance_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_opening_balance_id')->constrained(indexName: 'inventory_opening_line_batch_fk')->restrictOnDelete();
            $table->foreignId('product_service_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('total_cost', 19, 4);
            $table->timestamps();
            $table->unique(['inventory_opening_balance_id', 'product_service_id'], 'inventory_opening_line_product_unique');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_opening_balance_line_id')->nullable()->constrained(indexName: 'inventory_movement_opening_line_fk')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('inventory_movements')->restrictOnDelete();
            $table->foreignId('product_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 40)->index();
            $table->date('movement_date');
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('total_cost', 19, 4);
            $table->string('status', 30)->default('posted')->index();
            $table->timestamp('posted_at');
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique('reversal_of_id');
            $table->index(['product_service_id', 'warehouse_id', 'movement_date'], 'inventory_movement_product_warehouse_date_index');
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('opening_balance_line_id')->nullable()->unique()->constrained('inventory_opening_balance_lines', indexName: 'inventory_balance_opening_line_fk')->restrictOnDelete();
            $table->decimal('quantity_on_hand', 19, 4)->default(0);
            $table->decimal('weighted_average_cost', 19, 4)->default(0);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['product_service_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_opening_balance_lines');
        Schema::dropIfExists('inventory_opening_balances');
    }
};
