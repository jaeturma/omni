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
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()->constrained(indexName: 'inventory_transfer_number_reservation_fk')->restrictOnDelete();
            $table->string('transfer_number', 150)->nullable()->unique();
            $table->date('transfer_date');
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('in_transit_at')->nullable();
            $table->foreignId('in_transit_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['source_warehouse_id', 'status', 'transfer_date'], 'inventory_transfer_source_status_date_index');
            $table->index(['destination_warehouse_id', 'status', 'transfer_date'], 'inventory_transfer_destination_status_date_index');
        });
        Schema::create('inventory_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained(indexName: 'inventory_transfer_line_header_fk')->restrictOnDelete();
            $table->foreignId('product_service_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->decimal('quantity', 19, 4);
            $table->decimal('source_unit_cost', 19, 4)->nullable();
            $table->decimal('total_cost', 19, 4)->nullable();
            $table->timestamps();
            $table->unique(['inventory_transfer_id', 'product_service_id'], 'inventory_transfer_line_product_unique');
        });
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('inventory_transfer_line_id')->nullable()->after('inventory_adjustment_line_id')
                ->constrained(indexName: 'inventory_movement_transfer_line_fk')->restrictOnDelete();
            $table->index('inventory_transfer_line_id', 'inventory_movement_transfer_line_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_transfer_line_id');
        });
        Schema::dropIfExists('inventory_transfer_lines');
        Schema::dropIfExists('inventory_transfers');
    }
};
