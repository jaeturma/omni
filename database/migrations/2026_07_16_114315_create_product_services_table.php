<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_services', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('barcode', 100)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20)->index();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->decimal('default_cost', 19, 4)->default(0);
            $table->decimal('selling_price', 19, 4)->default(0);
            $table->decimal('reorder_level', 19, 4)->default(0);
            $table->boolean('is_inventory')->default(false)->index();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['type', 'status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_services');
    }
};
