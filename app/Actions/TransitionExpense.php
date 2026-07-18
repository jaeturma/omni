<?php

namespace App\Actions;

use App\Enums\ExpenseStatus;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionExpense
{
    public function __construct(private IssueDocumentNumber $issueNumber) {}

    /** @param array<string, mixed> $data */
    public function handle(Expense $expense, ExpenseStatus $target, int $userId, array $data = []): Expense
    {
        return DB::transaction(function () use ($expense, $target, $userId, $data): Expense {
            $locked = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This expense transition is not allowed.']);
            }
            $changes = ['status' => $target, 'updated_by' => $userId];
            if (in_array($target, [ExpenseStatus::Approved, ExpenseStatus::Paid, ExpenseStatus::Reimbursable], true)) {
                $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($locked->fiscal_period_id);
                if ($period->status !== 'open' || $locked->expense_date->lt($period->starts_on) || $locked->expense_date->gt($period->ends_on)) {
                    throw ValidationException::withMessages(['fiscal_period_id' => 'The expense date must belong to an open fiscal period.']);
                }
                if (! $locked->expense_number) {
                    $sequence = DocumentSequence::query()->where('document_type', 'expense_voucher')->where('active', true)->where('fiscal_year_id', $period->fiscal_year_id)->first();
                    if (! $sequence) {
                        throw ValidationException::withMessages(['status' => 'Configure an active expense-voucher sequence for this fiscal year.']);
                    }
                    $reservation = $this->issueNumber->handle($sequence, $userId);
                    $changes += ['expense_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id];
                }
            }
            if ($target === ExpenseStatus::Approved) {
                $changes += ['approved_at' => now(), 'approved_by' => $userId];
            }
            if ($target === ExpenseStatus::Reimbursable) {
                $changes += ['reimbursable' => true, 'approved_at' => now(), 'approved_by' => $userId];
            }
            if ($target === ExpenseStatus::Paid) {
                $withholding = (string) ($data['withholding_amount'] ?? $locked->withholding_amount);
                $deductions = (string) ($data['other_deductions'] ?? $locked->other_deductions);
                $cash = (string) ($data['net_cash_paid'] ?? $locked->net_cash_paid);
                if (bccomp(bcadd(bcadd($withholding, $deductions, 4), $cash, 4), $locked->gross_amount, 4) !== 0) {
                    throw ValidationException::withMessages(['gross_amount' => 'Gross amount must equal cash paid plus withholding and other deductions.']);
                }
                $paymentMethodId = $data['payment_method_id'] ?? $locked->payment_method_id;
                if (! $paymentMethodId) {
                    throw ValidationException::withMessages(['payment_method_id' => 'A payment method is required to mark an expense paid.']);
                }
                $changes += ['payment_method_id' => $paymentMethodId, 'bank_id' => $data['bank_id'] ?? $locked->bank_id, 'withholding_amount' => $withholding, 'other_deductions' => $deductions, 'net_cash_paid' => $cash, 'paid_at' => now(), 'paid_by' => $userId];
            }
            if ($target === ExpenseStatus::Voided) {
                $changes += ['voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $data['reason']];
            }
            $locked->update($changes);

            return $locked->fresh(['fiscalPeriod', 'supplier', 'paymentMethod', 'bank']);
        }, 3);
    }
}
