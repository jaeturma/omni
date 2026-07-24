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
        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('custodian_id')->constrained('users')->restrictOnDelete();
            $table->decimal('approved_fund_limit', 19, 4);
            $table->decimal('current_operational_balance', 19, 4);
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('petty_cash_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_fund_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_number_reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('voucher_number', 150)->nullable()->unique();
            $table->date('voucher_date');
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->string('payee');
            $table->string('expense_category', 50)->index();
            $table->text('purpose');
            $table->decimal('amount_released', 19, 4);
            $table->decimal('amount_liquidated', 19, 4)->default(0);
            $table->decimal('amount_returned', 19, 4)->default(0);
            $table->boolean('receipt_available')->default(false);
            $table->foreignId('expense_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('liquidated_at')->nullable();
            $table->foreignId('liquidated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('overdue_at')->nullable();
            $table->foreignId('overdue_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['petty_cash_fund_id', 'status', 'voucher_date']);
        });

        Schema::create('petty_cash_replenishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_fund_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->date('replenishment_date');
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->string('reference_number')->nullable();
            $table->string('status', 30)->default('posted')->index();
            $table->timestamp('posted_at');
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['petty_cash_fund_id', 'replenishment_date']);
        });

        Schema::create('petty_cash_replenishment_voucher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_replenishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('petty_cash_voucher_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->timestamps();
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('fund_transfer_id')->nullable()->change();
            $table->foreignId('petty_cash_voucher_id')->nullable()->after('fund_transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('petty_cash_replenishment_id')->nullable()->after('petty_cash_voucher_id')->constrained()->restrictOnDelete();
            $table->unique(['petty_cash_voucher_id', 'type']);
            $table->unique(['petty_cash_replenishment_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropUnique(['petty_cash_voucher_id', 'type']);
            $table->dropUnique(['petty_cash_replenishment_id', 'type']);
            $table->dropConstrainedForeignId('petty_cash_voucher_id');
            $table->dropConstrainedForeignId('petty_cash_replenishment_id');
            $table->unsignedBigInteger('fund_transfer_id')->nullable(false)->change();
        });
        Schema::dropIfExists('petty_cash_replenishment_voucher');
        Schema::dropIfExists('petty_cash_replenishments');
        Schema::dropIfExists('petty_cash_vouchers');
        Schema::dropIfExists('petty_cash_funds');
    }
};
