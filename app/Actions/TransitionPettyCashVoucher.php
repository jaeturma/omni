<?php

namespace App\Actions;

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\FinancialAccountType;
use App\Enums\PettyCashVoucherStatus;
use App\Models\CashTransaction;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\PettyCashFund;
use App\Models\PettyCashVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionPettyCashVoucher
{
    public function __construct(private IssueDocumentNumber $issue) {}

    /** @param array<string, mixed> $data */
    public function handle(PettyCashVoucher $voucher, PettyCashVoucherStatus $target, int $userId, array $data = []): PettyCashVoucher
    {
        return DB::transaction(function () use ($voucher, $target, $userId, $data): PettyCashVoucher {
            $locked = PettyCashVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This petty-cash voucher transition is not allowed.']);
            }
            $fund = PettyCashFund::query()->lockForUpdate()->findOrFail($locked->petty_cash_fund_id);
            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($fund->financial_account_id);

            match ($target) {
                PettyCashVoucherStatus::Released => $this->release($locked, $fund, $account, $userId),
                PettyCashVoucherStatus::Liquidated => $this->liquidate($locked, $fund, $account, $userId, $data),
                PettyCashVoucherStatus::Overdue => $locked->update(['status' => $target, 'overdue_at' => now(), 'overdue_by' => $userId, 'updated_by' => $userId]),
                PettyCashVoucherStatus::Voided => $this->void($locked, $fund, $account, $userId, $data['reason']),
                default => throw ValidationException::withMessages(['status' => 'This petty-cash voucher transition is unavailable.']),
            };

            return $locked->fresh(['fund.financialAccount', 'expense', 'transactions', 'replenishments', 'fiscalPeriod']);
        }, 3);
    }

    private function release(PettyCashVoucher $voucher, PettyCashFund $fund, FinancialAccount $account, int $userId): void
    {
        if (! $fund->active || ! $account->active || $account->type !== FinancialAccountType::PettyCash) {
            throw ValidationException::withMessages(['petty_cash_fund_id' => 'The fund must use an active dedicated petty-cash account.']);
        }
        if (bccomp($fund->current_operational_balance, $voucher->amount_released, 4) < 0
            || bccomp($account->current_balance ?? $account->opening_balance, $voucher->amount_released, 4) < 0) {
            throw ValidationException::withMessages(['amount_released' => 'The petty-cash fund has insufficient available balance.']);
        }

        $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($voucher->fiscal_period_id);
        if ($period->status !== 'open' || ! $voucher->voucher_date->betweenIncluded($period->starts_on, $period->ends_on)) {
            throw ValidationException::withMessages(['fiscal_period_id' => 'The voucher date must belong to an open fiscal period.']);
        }
        $sequence = DocumentSequence::query()->where('document_type', 'petty_cash_voucher')->where('active', true)->where('fiscal_year_id', $period->fiscal_year_id)->first();
        if (! $sequence) {
            throw ValidationException::withMessages(['status' => 'Configure an active petty-cash voucher sequence for this fiscal year.']);
        }
        $reservation = $this->issue->handle($sequence, $userId);

        $fund->update(['current_operational_balance' => bcsub($fund->current_operational_balance, $voucher->amount_released, 4), 'updated_by' => $userId]);
        $account->update(['current_balance' => bcsub($account->current_balance ?? $account->opening_balance, $voucher->amount_released, 4), 'updated_by' => $userId]);
        $voucher->transactions()->create([
            'financial_account_id' => $account->id, 'type' => CashTransactionType::PettyCashRelease,
            'transaction_date' => $voucher->voucher_date, 'amount' => $voucher->amount_released,
            'status' => CashTransactionStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
        ]);
        $voucher->update([
            'document_number_reservation_id' => $reservation->id, 'voucher_number' => $reservation->document_number,
            'status' => PettyCashVoucherStatus::Released, 'released_at' => now(), 'released_by' => $userId, 'updated_by' => $userId,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function liquidate(PettyCashVoucher $voucher, PettyCashFund $fund, FinancialAccount $account, int $userId, array $data): void
    {
        if (bccomp(bcadd((string) $data['amount_liquidated'], (string) $data['amount_returned'], 4), $voucher->amount_released, 4) !== 0) {
            throw ValidationException::withMessages(['amount_liquidated' => 'Liquidated and returned amounts must equal the amount released.']);
        }
        if (! empty($data['expense_id'])) {
            $expense = Expense::query()->lockForUpdate()->find($data['expense_id']);
            if (! $expense || ! in_array($expense->status->value, ['approved', 'paid', 'reimbursable'], true)
                || $expense->payee_name !== $voucher->payee
                || bccomp($expense->net_cash_paid, (string) $data['amount_liquidated'], 4) !== 0) {
                throw ValidationException::withMessages(['expense_id' => 'The linked expense is no longer eligible for liquidation.']);
            }
        }

        $returned = (string) $data['amount_returned'];
        if (bccomp($returned, '0.0000', 4) > 0) {
            $fund->update(['current_operational_balance' => bcadd($fund->current_operational_balance, $returned, 4), 'updated_by' => $userId]);
            $account->update(['current_balance' => bcadd($account->current_balance ?? $account->opening_balance, $returned, 4), 'updated_by' => $userId]);
            $voucher->transactions()->create([
                'financial_account_id' => $account->id, 'type' => CashTransactionType::PettyCashReturn,
                'transaction_date' => now()->toDateString(), 'amount' => $returned,
                'status' => CashTransactionStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
            ]);
        }
        $voucher->update([
            'amount_liquidated' => $data['amount_liquidated'], 'amount_returned' => $returned,
            'receipt_available' => $data['receipt_available'], 'expense_id' => $data['expense_id'] ?? null,
            'status' => PettyCashVoucherStatus::Liquidated, 'liquidated_at' => now(), 'liquidated_by' => $userId, 'updated_by' => $userId,
        ]);
    }

    private function void(PettyCashVoucher $voucher, PettyCashFund $fund, FinancialAccount $account, int $userId, string $reason): void
    {
        if ($voucher->replenishments()->exists()) {
            throw ValidationException::withMessages(['status' => 'A replenished voucher cannot be voided.']);
        }
        $restore = match ($voucher->status) {
            PettyCashVoucherStatus::Released, PettyCashVoucherStatus::Overdue => $voucher->amount_released,
            PettyCashVoucherStatus::Liquidated => $voucher->amount_liquidated,
            default => '0.0000',
        };
        if (bccomp($restore, '0.0000', 4) > 0) {
            $fund->update(['current_operational_balance' => bcadd($fund->current_operational_balance, $restore, 4), 'updated_by' => $userId]);
            $account->update(['current_balance' => bcadd($account->current_balance ?? $account->opening_balance, $restore, 4), 'updated_by' => $userId]);
        }
        CashTransaction::query()->whereBelongsTo($voucher, 'pettyCashVoucher')->lockForUpdate()->get()->each->update([
            'status' => CashTransactionStatus::Voided, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $reason,
        ]);
        $voucher->update(['status' => PettyCashVoucherStatus::Voided, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $reason, 'updated_by' => $userId]);
    }
}
