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
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->index(['invoice_date', 'due_date', 'id'], 'sales_invoice_aging_order_index');
        });
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->index(['invoice_date', 'due_date', 'id'], 'supplier_invoice_aging_order_index');
        });
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->index(['status', 'journal_date', 'id'], 'journal_status_date_order_index');
        });
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['occurred_at', 'id'], 'audit_occurred_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_occurred_order_index');
        });
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropIndex('journal_status_date_order_index');
        });
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropIndex('supplier_invoice_aging_order_index');
        });
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropIndex('sales_invoice_aging_order_index');
        });
    }
};
