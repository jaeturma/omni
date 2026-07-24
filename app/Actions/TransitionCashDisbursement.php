<?php

namespace App\Actions;

use App\Enums\CashDisbursementSourceType;
use App\Enums\CashDisbursementStatus;
use App\Models\CashDisbursement;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionCashDisbursement
{
    public function __construct(private IssueDocumentNumber $issue) {}

    /** @param array<string, mixed> $data */
    public function handle(CashDisbursement $disbursement, CashDisbursementStatus $target, int $userId, array $data = []): CashDisbursement
    {
        return DB::transaction(function () use ($disbursement, $target, $userId, $data): CashDisbursement {
            $locked = CashDisbursement::query()->lockForUpdate()->findOrFail($disbursement->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This disbursement transition is not allowed.']);
            }

            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($locked->financial_account_id);
            $changes = ['status' => $target, 'updated_by' => $userId];

            if ($target === CashDisbursementStatus::Posted) {
                if (! $account->active || ! $account->allow_disbursements) {
                    throw ValidationException::withMessages(['financial_account_id' => 'The financial account cannot make disbursements.']);
                }
                $this->validateLockedSource($locked);
                $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($locked->fiscal_period_id);
                if ($period->status !== 'open' || ! $locked->disbursement_date->betweenIncluded($period->starts_on, $period->ends_on)) {
                    throw ValidationException::withMessages(['fiscal_period_id' => 'The disbursement date must belong to an open fiscal period.']);
                }
                $sequence = DocumentSequence::query()->where('document_type', 'cash_disbursement')->where('active', true)->where('fiscal_year_id', $period->fiscal_year_id)->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active cash disbursement sequence for this fiscal year.']);
                }
                $reservation = $this->issue->handle($sequence, $userId);
                $account->update(['current_balance' => bcsub($account->current_balance ?? $account->opening_balance, $locked->net_cash_out, 4), 'updated_by' => $userId]);
                $changes += ['disbursement_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'posted_at' => now(), 'posted_by' => $userId];
            } elseif ($target === CashDisbursementStatus::Released) {
                $changes += ['release_date' => $data['release_date'], 'released_at' => now(), 'released_by' => $userId];
            } elseif ($target === CashDisbursementStatus::Cleared) {
                $changes += ['clearing_date' => $data['clearing_date'], 'cleared_at' => now(), 'cleared_by' => $userId];
            } else {
                $account->update(['current_balance' => bcadd($account->current_balance ?? $account->opening_balance, $locked->net_cash_out, 4), 'updated_by' => $userId]);
                $changes += $target === CashDisbursementStatus::Stopped
                    ? ['stopped_at' => now(), 'stopped_by' => $userId, 'stop_reason' => $data['reason']]
                    : ['voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $data['reason']];
            }

            $locked->update($changes);

            return $locked->fresh(['financialAccount', 'supplierPayment', 'expense', 'paymentMethod', 'fiscalPeriod']);
        }, 3);
    }

    private function validateLockedSource(CashDisbursement $disbursement): void
    {
        if ($disbursement->source_type === CashDisbursementSourceType::SupplierPayment) {
            $payment = SupplierPayment::query()->with('supplier')->lockForUpdate()->find($disbursement->supplier_payment_id);
            if (! $payment || in_array($payment->status->value, ['draft', 'voided'], true)
                || $payment->supplier->name !== $disbursement->payee
                || bccomp($payment->net_cash_paid, $disbursement->net_cash_out, 4) !== 0) {
                throw ValidationException::withMessages(['supplier_payment_id' => 'The linked supplier payment is no longer eligible for posting.']);
            }
        }

        if ($disbursement->source_type === CashDisbursementSourceType::Expense) {
            $expense = Expense::query()->lockForUpdate()->find($disbursement->expense_id);
            if (! $expense || ! in_array($expense->status->value, ['approved', 'paid', 'reimbursable'], true)
                || $expense->payee_name !== $disbursement->payee
                || bccomp($expense->net_cash_paid, $disbursement->net_cash_out, 4) !== 0) {
                throw ValidationException::withMessages(['expense_id' => 'The linked expense is no longer eligible for posting.']);
            }
        }
    }
}
