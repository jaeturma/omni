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
        Schema::create('physical_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()
                ->constrained(indexName: 'physical_count_number_reservation_fk')->restrictOnDelete();
            $table->string('count_number', 150)->nullable()->unique();
            $table->date('count_date');
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->timestamp('cutoff_at');
            $table->boolean('blind_count')->default(false);
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('counting_started_at')->nullable();
            $table->foreignId('counting_started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
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
            $table->index(['warehouse_id', 'status', 'count_date'], 'physical_count_warehouse_status_date_index');
        });
        Schema::create('physical_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_count_id')->constrained(indexName: 'physical_count_line_header_fk')->restrictOnDelete();
            $table->foreignId('product_service_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->decimal('system_quantity_snapshot', 19, 4);
            $table->decimal('counted_quantity', 19, 4)->nullable();
            $table->decimal('variance_quantity', 19, 4)->nullable();
            $table->decimal('unit_cost_snapshot', 19, 4);
            $table->decimal('variance_value', 19, 4)->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->unique(['physical_count_id', 'product_service_id'], 'physical_count_line_product_unique');
        });
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('physical_count_line_id')->nullable()->after('inventory_transfer_line_id')
                ->constrained(indexName: 'inventory_movement_physical_count_line_fk')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('physical_count_line_id');
        });
        Schema::dropIfExists('physical_count_lines');
        Schema::dropIfExists('physical_counts');
    }
};
