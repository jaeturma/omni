<?php

namespace App\Actions;

use App\Enums\CashReceiptStatus;
use App\Models\CashReceipt;
use App\Models\DocumentSequence;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionCashReceipt
{
    public function __construct(private IssueDocumentNumber $issue) {}

    /** @param array<string, mixed> $data */
    public function handle(CashReceipt $receipt, CashReceiptStatus $target, int $userId, array $data = []): CashReceipt
    {
        return DB::transaction(function () use ($receipt, $target, $userId, $data): CashReceipt {
            $locked = CashReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This receipt transition is not allowed.']);
            }
            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($locked->financial_account_id);
            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === CashReceiptStatus::Posted) {
                if (! $account->active || ! $account->allow_receipts) {
                    throw ValidationException::withMessages(['financial_account_id' => 'The financial account cannot accept receipts.']);
                }
                $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($locked->fiscal_period_id);
                if ($period->status !== 'open' || ! $locked->receipt_date->betweenIncluded($period->starts_on, $period->ends_on)) {
                    throw ValidationException::withMessages(['fiscal_period_id' => 'The receipt date must belong to an open fiscal period.']);
                }
                $sequence = DocumentSequence::query()->where('document_type', 'cash_receipt')->where('active', true)->where('fiscal_year_id', $period->fiscal_year_id)->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active cash receipt sequence for this fiscal year.']);
                }
                $reservation = $this->issue->handle($sequence, $userId);
                $balance = bcadd($account->current_balance ?? $account->opening_balance, $locked->net_amount_deposited, 4);
                $account->update(['current_balance' => $balance, 'updated_by' => $userId]);
                $changes += ['receipt_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'posted_at' => now(), 'posted_by' => $userId];
            } elseif ($target === CashReceiptStatus::Cleared) {
                $changes += ['clearing_date' => $data['clearing_date'], 'cleared_at' => now(), 'cleared_by' => $userId];
            } else {
                $account->update(['current_balance' => bcsub($account->current_balance ?? $account->opening_balance, $locked->net_amount_deposited, 4), 'updated_by' => $userId]);
                $changes += $target === CashReceiptStatus::Bounced
                    ? ['bounced_at' => now(), 'bounced_by' => $userId, 'bounce_reason' => $data['reason']]
                    : ['voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $data['reason']];
            }
            $locked->update($changes);

            return $locked->fresh(['financialAccount', 'customer', 'customerPayment', 'paymentMethod', 'fiscalPeriod']);
        }, 3);
    }
}
