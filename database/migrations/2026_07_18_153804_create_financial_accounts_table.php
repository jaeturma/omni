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
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 40)->index();
            $table->foreignId('bank_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('branch_name')->nullable();
            $table->text('account_number')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->decimal('opening_balance', 19, 4)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->boolean('allow_receipts')->default(true);
            $table->boolean('allow_disbursements')->default(true);
            $table->boolean('allow_transfers')->default(true);
            $table->boolean('allow_reconciliation')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('opening_balance_set_at')->nullable();
            $table->foreignId('opening_balance_set_by')->nullable()->constrained('users');
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users');
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users');
            $table->text('deactivation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
