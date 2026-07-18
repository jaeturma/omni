<?php

namespace App\Actions;

use App\Models\Expense;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SaveExpense
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId, ?Expense $expense = null): Expense
    {
        return DB::transaction(function () use ($data, $userId, $expense): Expense {
            $supplier = ! empty($data['supplier_id']) ? Supplier::query()->findOrFail($data['supplier_id']) : null;
            $header = ['fiscal_period_id' => $data['fiscal_period_id'], 'supplier_id' => $supplier?->id, 'customer_id' => $data['customer_id'] ?? null, 'payment_method_id' => $data['payment_method_id'] ?? null, 'bank_id' => $data['bank_id'] ?? null, 'expense_date' => $data['expense_date'], 'payee_name' => $supplier ? $supplier->name : $data['payee_name'], 'expense_category' => $data['expense_category'], 'description' => $data['description'], 'business_purpose' => $data['business_purpose'], 'reference_number' => $data['reference_number'] ?? null, 'project_reference' => $data['project_reference'] ?? null, 'receipt_available' => $data['receipt_available'] ?? false, 'receipt_reference' => $data['receipt_reference'] ?? null, 'gross_amount' => $data['gross_amount'], 'withholding_amount' => $data['withholding_amount'] ?? '0', 'other_deductions' => $data['other_deductions'] ?? '0', 'net_cash_paid' => $data['net_cash_paid'] ?? '0', 'reimbursable' => $data['reimbursable'] ?? false, 'notes' => $data['notes'] ?? null, 'updated_by' => $userId];
            if ($expense) {
                $expense->update($header);
            } else {
                $expense = Expense::query()->create($header + ['created_by' => $userId]);
            }

            return $expense;
        });
    }
}
