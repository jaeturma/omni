<?php

namespace App\Actions;

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\PettyCashVoucherStatus;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\PettyCashFund;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashVoucher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplenishPettyCash
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId): PettyCashReplenishment
    {
        return DB::transaction(function () use ($data, $userId): PettyCashReplenishment {
            $fund = PettyCashFund::query()->lockForUpdate()->findOrFail($data['petty_cash_fund_id']);
            $accounts = FinancialAccount::query()->whereKey([$fund->financial_account_id, $data['source_financial_account_id']])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $pettyAccount = $accounts->get($fund->financial_account_id);
            $sourceAccount = $accounts->get($data['source_financial_account_id']);
            if (! $fund->active || ! $pettyAccount || ! $sourceAccount || $pettyAccount->is($sourceAccount)
                || ! $pettyAccount->active || ! $sourceAccount->active || ! $sourceAccount->allow_transfers) {
                throw ValidationException::withMessages(['source_financial_account_id' => 'Select an active source account different from the petty-cash account.']);
            }

            $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($data['fiscal_period_id']);
            if ($period->status !== 'open' || ! $period->starts_on->lte($data['replenishment_date']) || ! $period->ends_on->gte($data['replenishment_date'])) {
                throw ValidationException::withMessages(['fiscal_period_id' => 'The replenishment date must belong to an open fiscal period.']);
            }

            $vouchers = $this->lockVouchers($data['voucher_ids']);
            $amount = '0.0000';
            foreach ($vouchers as $voucher) {
                if ((int) $voucher->petty_cash_fund_id !== $fund->id || $voucher->status !== PettyCashVoucherStatus::Liquidated || $voucher->replenishments()->exists()) {
                    throw ValidationException::withMessages(['voucher_ids' => 'Only unreplenished liquidated vouchers from this fund may be selected.']);
                }
                $amount = bcadd($amount, $voucher->amount_liquidated, 4);
            }
            if ($vouchers->count() !== count($data['voucher_ids']) || bccomp($amount, '0.0000', 4) <= 0) {
                throw ValidationException::withMessages(['voucher_ids' => 'Select at least one eligible voucher with a liquidated amount.']);
            }
            if (bccomp($sourceAccount->current_balance ?? $sourceAccount->opening_balance, $amount, 4) < 0) {
                throw ValidationException::withMessages(['voucher_ids' => 'The replenishment source account has insufficient balance.']);
            }
            if (bccomp(bcadd($fund->current_operational_balance, $amount, 4), $fund->approved_fund_limit, 4) > 0) {
                throw ValidationException::withMessages(['voucher_ids' => 'This replenishment would exceed the approved fund limit.']);
            }

            $replenishment = PettyCashReplenishment::query()->create([
                'petty_cash_fund_id' => $fund->id, 'source_financial_account_id' => $sourceAccount->id,
                'replenishment_date' => $data['replenishment_date'], 'fiscal_period_id' => $period->id,
                'amount' => $amount, 'reference_number' => $data['reference_number'] ?? null,
                'status' => CashTransactionStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
            ]);
            $replenishment->vouchers()->attach($vouchers->mapWithKeys(fn (PettyCashVoucher $voucher): array => [$voucher->id => ['amount' => $voucher->amount_liquidated]])->all());

            $sourceAccount->update(['current_balance' => bcsub($sourceAccount->current_balance ?? $sourceAccount->opening_balance, $amount, 4), 'updated_by' => $userId]);
            $pettyAccount->update(['current_balance' => bcadd($pettyAccount->current_balance ?? $pettyAccount->opening_balance, $amount, 4), 'updated_by' => $userId]);
            $fund->update(['current_operational_balance' => bcadd($fund->current_operational_balance, $amount, 4), 'updated_by' => $userId]);
            $replenishment->transactions()->createMany([
                ['financial_account_id' => $sourceAccount->id, 'type' => CashTransactionType::TransferOut, 'transaction_date' => $data['replenishment_date'],
                    'amount' => $amount, 'reference_number' => $data['reference_number'] ?? null, 'status' => CashTransactionStatus::Posted,
                    'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId],
                ['financial_account_id' => $pettyAccount->id, 'type' => CashTransactionType::PettyCashReplenishment, 'transaction_date' => $data['replenishment_date'],
                    'amount' => $amount, 'reference_number' => $data['reference_number'] ?? null, 'status' => CashTransactionStatus::Posted,
                    'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId],
            ]);

            return $replenishment->fresh(['fund.financialAccount', 'sourceFinancialAccount', 'vouchers', 'transactions']);
        }, 3);
    }

    /** @param list<int> $voucherIds
     * @return Collection<int, PettyCashVoucher>
     */
    private function lockVouchers(array $voucherIds): Collection
    {
        return PettyCashVoucher::query()->whereKey($voucherIds)->orderBy('id')->lockForUpdate()->get();
    }
}
