<?php

namespace App\Actions;

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\FundTransferStatus;
use App\Models\CashTransaction;
use App\Models\DocumentSequence;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\FundTransfer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionFundTransfer
{
    public function __construct(private IssueDocumentNumber $issue) {}

    /** @param array<string, mixed> $data */
    public function handle(FundTransfer $transfer, FundTransferStatus $target, int $userId, array $data = []): FundTransfer
    {
        return DB::transaction(function () use ($transfer, $target, $userId, $data): FundTransfer {
            $locked = FundTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This fund transfer transition is not allowed.']);
            }

            $accounts = $this->lockAccounts($locked);

            match ($target) {
                FundTransferStatus::Posted => $this->post($locked, $accounts, $userId),
                FundTransferStatus::Completed => $this->complete($locked, $accounts, $userId),
                FundTransferStatus::Failed => $this->reverse($locked, $accounts, $userId, FundTransferStatus::Failed, $data['reason']),
                FundTransferStatus::Voided => $this->reverse($locked, $accounts, $userId, FundTransferStatus::Voided, $data['reason']),
                default => throw ValidationException::withMessages(['status' => 'This fund transfer transition is not available.']),
            };

            return $locked->fresh(['sourceFinancialAccount', 'destinationFinancialAccount', 'sourceTransaction', 'destinationTransaction', 'fiscalPeriod']);
        }, 3);
    }

    /** @return Collection<int, FinancialAccount> */
    private function lockAccounts(FundTransfer $transfer): Collection
    {
        return FinancialAccount::query()
            ->whereKey([$transfer->source_financial_account_id, $transfer->destination_financial_account_id])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /** @param Collection<int, FinancialAccount> $accounts */
    private function post(FundTransfer $transfer, Collection $accounts, int $userId): void
    {
        $source = $accounts->get($transfer->source_financial_account_id);
        $destination = $accounts->get($transfer->destination_financial_account_id);
        if (! $source || ! $destination || ! $source->active || ! $destination->active || ! $source->allow_transfers || ! $destination->allow_transfers) {
            throw ValidationException::withMessages(['source_financial_account_id' => 'Both financial accounts must be active and allow transfers.']);
        }
        if ($source->is($destination)) {
            throw ValidationException::withMessages(['destination_financial_account_id' => 'The destination account must differ from the source account.']);
        }
        if ($source->currency !== $destination->currency) {
            throw ValidationException::withMessages(['destination_financial_account_id' => 'Transfers require accounts with the same currency.']);
        }

        $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($transfer->fiscal_period_id);
        if ($period->status !== 'open'
            || ! $transfer->transfer_date->betweenIncluded($period->starts_on, $period->ends_on)
            || ! $transfer->destination_date->betweenIncluded($period->starts_on, $period->ends_on)) {
            throw ValidationException::withMessages(['fiscal_period_id' => 'Both transfer dates must belong to the selected open fiscal period.']);
        }

        $totalOut = bcadd($transfer->amount, $transfer->transfer_fee, 4);
        $sourceBalance = $source->current_balance ?? $source->opening_balance;
        if (bccomp($sourceBalance, $totalOut, 4) < 0) {
            throw ValidationException::withMessages(['amount' => 'The source account has insufficient operational balance for the transfer and fee.']);
        }

        $sequence = DocumentSequence::query()->where('document_type', 'fund_transfer')->where('active', true)->where('fiscal_year_id', $period->fiscal_year_id)->first();
        if (! $sequence) {
            throw ValidationException::withMessages(['status' => 'Configure an active fund transfer sequence for this fiscal year.']);
        }
        $reservation = $this->issue->handle($sequence, $userId);
        $sameDay = $transfer->transfer_date->isSameDay($transfer->destination_date);

        $source->update(['current_balance' => bcsub($sourceBalance, $totalOut, 4), 'updated_by' => $userId]);
        if ($sameDay) {
            $destination->update(['current_balance' => bcadd($destination->current_balance ?? $destination->opening_balance, $transfer->amount, 4), 'updated_by' => $userId]);
        }

        $transfer->transactions()->createMany([
            ['financial_account_id' => $source->id, 'type' => CashTransactionType::TransferOut, 'transaction_date' => $transfer->transfer_date,
                'amount' => $transfer->amount, 'fee_amount' => $transfer->transfer_fee, 'reference_number' => $transfer->reference_number,
                'status' => CashTransactionStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId],
            ['financial_account_id' => $destination->id, 'type' => CashTransactionType::TransferIn, 'transaction_date' => $transfer->destination_date,
                'amount' => $transfer->amount, 'fee_amount' => '0.0000', 'reference_number' => $transfer->reference_number,
                'status' => $sameDay ? CashTransactionStatus::Posted : CashTransactionStatus::Draft,
                'posted_at' => $sameDay ? now() : null, 'posted_by' => $sameDay ? $userId : null, 'created_by' => $userId],
        ]);

        $transfer->update([
            'document_number_reservation_id' => $reservation->id, 'transfer_number' => $reservation->document_number,
            'status' => $sameDay ? FundTransferStatus::Completed : FundTransferStatus::InTransit,
            'posted_at' => now(), 'posted_by' => $userId, 'completed_at' => $sameDay ? now() : null,
            'completed_by' => $sameDay ? $userId : null, 'updated_by' => $userId,
        ]);
    }

    /** @param Collection<int, FinancialAccount> $accounts */
    private function complete(FundTransfer $transfer, Collection $accounts, int $userId): void
    {
        $destination = $accounts->get($transfer->destination_financial_account_id);
        if (! $destination || ! $destination->active || ! $destination->allow_transfers) {
            throw ValidationException::withMessages(['destination_financial_account_id' => 'The destination account must be active and allow transfers.']);
        }

        $destinationTransaction = CashTransaction::query()->lockForUpdate()
            ->whereBelongsTo($transfer)->where('type', CashTransactionType::TransferIn)->firstOrFail();
        $destination->update([
            'current_balance' => bcadd($destination->current_balance ?? $destination->opening_balance, $transfer->amount, 4),
            'updated_by' => $userId,
        ]);
        $destinationTransaction->update(['status' => CashTransactionStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId]);
        $transfer->update(['status' => FundTransferStatus::Completed, 'completed_at' => now(), 'completed_by' => $userId, 'updated_by' => $userId]);
    }

    /** @param Collection<int, FinancialAccount> $accounts */
    private function reverse(FundTransfer $transfer, Collection $accounts, int $userId, FundTransferStatus $target, string $reason): void
    {
        $source = $accounts->get($transfer->source_financial_account_id);
        $destination = $accounts->get($transfer->destination_financial_account_id);
        if (! $source || ! $destination) {
            throw ValidationException::withMessages(['status' => 'The linked transfer accounts are unavailable.']);
        }

        $source->update([
            'current_balance' => bcadd($source->current_balance ?? $source->opening_balance, bcadd($transfer->amount, $transfer->transfer_fee, 4), 4),
            'updated_by' => $userId,
        ]);
        if ($transfer->status === FundTransferStatus::Completed) {
            $destination->update([
                'current_balance' => bcsub($destination->current_balance ?? $destination->opening_balance, $transfer->amount, 4),
                'updated_by' => $userId,
            ]);
        }

        CashTransaction::query()->whereBelongsTo($transfer)->lockForUpdate()->get()->each->update([
            'status' => CashTransactionStatus::Voided, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $reason,
        ]);
        $transfer->update($target === FundTransferStatus::Failed
            ? ['status' => $target, 'failed_at' => now(), 'failed_by' => $userId, 'failure_reason' => $reason, 'updated_by' => $userId]
            : ['status' => $target, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $reason, 'updated_by' => $userId]);
    }
}
