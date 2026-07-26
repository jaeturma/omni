<?php

namespace App\Services;

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\AccountingSourceType;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Enums\PostingSourceType;
use App\Models\CashDisbursement;
use App\Models\CashReceipt;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\FundTransfer;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\JournalEntry;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashVoucher;
use App\Models\PhysicalCount;
use App\Models\ReceivingRecord;
use App\Models\SalesInvoice;
use App\Models\SourcePosting;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Support\AccountingWorkflow;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AutomaticSourcePosting
{
    public function __construct(
        private ResolvePostingRule $rules,
        private SaveJournalEntry $saveJournal,
        private TransitionJournalEntry $transitionJournal,
    ) {}

    public function shouldPost(Model $source, bool $created = false): bool
    {
        if ($source instanceof CashReceipt && $source->customer_payment_id !== null) {
            return false;
        }
        if ($source instanceof CashDisbursement && ($source->supplier_payment_id !== null || $source->expense_id !== null)) {
            return false;
        }
        if ($created) {
            return $source instanceof PettyCashReplenishment && $this->status($source) === 'posted';
        }
        if (! $source->wasChanged('status') && ! ($source instanceof FundTransfer && $source->wasChanged('posted_at'))) {
            return false;
        }

        return match ($source::class) {
            SalesInvoice::class, CustomerPayment::class, SupplierInvoice::class,
            SupplierPayment::class, CashReceipt::class, CashDisbursement::class,
            InventoryAdjustment::class, PhysicalCount::class => $this->status($source) === 'posted',
            Expense::class => $this->status($source) === 'paid',
            FundTransfer::class => $source->getAttribute('posted_at') !== null,
            PettyCashVoucher::class => $this->status($source) === 'liquidated',
            ReceivingRecord::class => in_array($this->status($source), ['accepted', 'partially_accepted'], true),
            Delivery::class => $this->status($source) === 'delivered',
            InventoryTransfer::class => $this->status($source) === 'completed',
            default => false,
        };
    }

    public function userId(Model $source): int
    {
        return (int) ($source->getAttribute('posted_by')
            ?? $source->getAttribute('paid_by')
            ?? $source->getAttribute('updated_by')
            ?? $source->getAttribute('created_by'));
    }

    public function attempt(Model $source, int $userId): SourcePosting
    {
        $sourceType = $this->sourceType($source);
        $posting = SourcePosting::query()->firstOrCreate([
            'source_type' => $sourceType,
            'source_id' => $source->getKey(),
        ]);

        try {
            return DB::transaction(function () use ($posting, $source, $userId, $sourceType): SourcePosting {
                $posting = SourcePosting::query()->lockForUpdate()->findOrFail($posting->id);
                if ($posting->status === 'posted') {
                    return $posting;
                }

                $posting->update([
                    'status' => 'processing',
                    'attempt_count' => $posting->attempt_count + 1,
                    'last_attempt_at' => now(),
                    'last_attempted_by' => $userId,
                    'failure_reason' => null,
                ]);

                $data = $this->journalData($source, $sourceType);
                $journal = $this->saveJournal->handle($data, $userId);
                $journal = $this->transitionJournal->handle($journal, JournalEntryStatus::Posted, $userId);
                $posting->update([
                    'status' => 'posted',
                    'journal_entry_id' => $journal->id,
                    'posted_at' => now(),
                ]);

                return $posting->refresh();
            }, 3);
        } catch (Throwable $exception) {
            SourcePosting::query()->whereKey($posting->id)->update([
                'status' => 'failed',
                'attempt_count' => $posting->attempt_count + 1,
                'last_attempt_at' => now(),
                'last_attempted_by' => $userId,
                'failure_reason' => Str::limit($exception->getMessage(), 2000, ''),
            ]);

            return $posting->refresh();
        }
    }

    public function retry(SourcePosting $posting, int $userId): SourcePosting
    {
        if ($posting->status !== 'failed') {
            return $posting;
        }

        $class = AccountingSourceType::from((string) $posting->getRawOriginal('source_type'))->modelClass();
        $source = $class::query()->findOrFail($posting->source_id);

        return $this->attempt($source, $userId);
    }

    public function rebuildLink(SourcePosting $posting, int $userId): SourcePosting
    {
        return DB::transaction(function () use ($posting, $userId): SourcePosting {
            $posting = SourcePosting::query()->lockForUpdate()->findOrFail($posting->id);
            $journal = JournalEntry::query()
                ->where('source_type', $posting->getRawOriginal('source_type'))
                ->where('source_id', $posting->source_id)
                ->sole();
            $posting->update([
                'journal_entry_id' => $journal->id,
                'status' => 'posted',
                'posted_at' => $journal->posted_at,
                'failure_reason' => null,
                'last_attempted_by' => $userId,
                'last_attempt_at' => now(),
            ]);

            return $posting->refresh();
        }, 3);
    }

    /** @return array<string, mixed> */
    private function journalData(Model $source, AccountingSourceType $sourceType): array
    {
        [$date, $reference, $description, $journalType, $components] = $this->components($source);
        $period = $this->fiscalPeriod($source, $date);
        AccountingWorkflow::assertPostingPeriod($period, $date);
        $lines = [];

        foreach ($components as $component) {
            $amount = bcadd((string) $component['amount'], '0', 4);
            if (bccomp($amount, '0', 4) <= 0) {
                continue;
            }
            $rule = $this->rules->resolve($component['rule'], $date, $component['dimensions'] ?? []);
            $lines[] = [
                'account_id' => $component['side'] === 'debit' ? $rule->debit_account_id : $rule->credit_account_id,
                'description' => $component['description'],
                'debit' => $component['side'] === 'debit' ? $amount : '0.0000',
                'credit' => $component['side'] === 'credit' ? $amount : '0.0000',
                'customer_id' => $source->getAttribute('customer_id'),
                'supplier_id' => $source->getAttribute('supplier_id'),
                'financial_account_id' => $component['dimensions']['financial_account_id'] ?? null,
                'warehouse_id' => $component['dimensions']['warehouse_id'] ?? null,
                'product_id' => $component['product_id'] ?? null,
                'source_line_type' => $component['source_line_type'] ?? null,
                'source_line_id' => $component['source_line_id'] ?? null,
            ];
        }

        return [
            'journal_number' => 'AUT-'.Str::upper(Str::limit($sourceType->value, 15, '')).'-'.$source->getKey(),
            'journal_date' => $date,
            'document_date' => $date,
            'fiscal_period_id' => $period->id,
            'journal_type' => $journalType,
            'source_type' => $sourceType,
            'source_id' => $source->getKey(),
            'reference_number' => $reference,
            'description' => $description,
            'lines' => $lines,
        ];
    }

    /** @return array{string, ?string, string, JournalEntryType, list<array<string, mixed>>} */
    private function components(Model $source): array
    {
        return match ($source::class) {
            SalesInvoice::class => $this->salesInvoiceComponents($source),
            CustomerPayment::class => $this->customerPaymentComponents($source),
            SupplierInvoice::class => $this->supplierInvoiceComponents($source),
            SupplierPayment::class => $this->supplierPaymentComponents($source),
            Expense::class => $this->expenseComponents($source),
            CashReceipt::class => $this->paired($source->receipt_date, $source->receipt_number, 'Cash receipt', JournalEntryType::CashReceipt, PostingSourceType::CashReceipt, (string) $source->gross_receipt, ['financial_account_id' => $source->financial_account_id]),
            CashDisbursement::class => $this->paired($source->disbursement_date, $source->disbursement_number, 'Cash disbursement', JournalEntryType::CashDisbursement, PostingSourceType::CashDisbursement, (string) $source->gross_settlement, ['financial_account_id' => $source->financial_account_id]),
            FundTransfer::class => $this->transferComponents($source),
            PettyCashVoucher::class => $this->paired($source->voucher_date, $source->voucher_number, 'Petty cash liquidation', JournalEntryType::Expense, PostingSourceType::OperatingExpense, (string) $source->amount_liquidated, ['expense_category' => $source->expense_category]),
            PettyCashReplenishment::class => $this->paired($source->replenishment_date, $source->reference_number, 'Petty cash replenishment', JournalEntryType::Transfer, PostingSourceType::Transfer, (string) $source->amount, ['financial_account_id' => $source->source_financial_account_id]),
            Delivery::class => $this->inventoryComponents($source, 'delivery_line_id', PostingSourceType::InventoryIssue, JournalEntryType::Inventory, $source->delivery_date, $source->delivery_number, 'Inventory sales issue'),
            ReceivingRecord::class => $this->inventoryComponents($source, 'receiving_record_line_id', PostingSourceType::InventoryReceipt, JournalEntryType::Inventory, $source->receiving_date, $source->receiving_number, 'Inventory purchase receipt'),
            InventoryAdjustment::class => $this->inventoryComponents($source, 'inventory_adjustment_line_id', PostingSourceType::InventoryAdjustment, JournalEntryType::Adjustment, $source->adjustment_date, $source->adjustment_number, 'Inventory adjustment'),
            InventoryTransfer::class => $this->inventoryComponents($source, 'inventory_transfer_line_id', PostingSourceType::InventoryAdjustment, JournalEntryType::Inventory, $source->transfer_date, $source->transfer_number, 'Warehouse transfer'),
            PhysicalCount::class => $this->physicalCountComponents($source),
            default => throw new \DomainException('This operational source type is not supported for automatic posting.'),
        };
    }

    private function sourceType(Model $source): AccountingSourceType
    {
        return match ($source::class) {
            SalesInvoice::class => AccountingSourceType::SalesInvoice,
            CustomerPayment::class => AccountingSourceType::CustomerPayment,
            SupplierInvoice::class => AccountingSourceType::SupplierInvoice,
            SupplierPayment::class => AccountingSourceType::SupplierPayment,
            Expense::class => AccountingSourceType::Expense,
            CashReceipt::class => AccountingSourceType::CashReceipt,
            CashDisbursement::class => AccountingSourceType::CashDisbursement,
            FundTransfer::class => AccountingSourceType::FundTransfer,
            Delivery::class => AccountingSourceType::Delivery,
            ReceivingRecord::class => AccountingSourceType::ReceivingRecord,
            InventoryAdjustment::class => AccountingSourceType::InventoryAdjustment,
            InventoryTransfer::class => AccountingSourceType::InventoryTransfer,
            PhysicalCount::class => AccountingSourceType::PhysicalCount,
            PettyCashVoucher::class => AccountingSourceType::PettyCashVoucher,
            PettyCashReplenishment::class => AccountingSourceType::PettyCashReplenishment,
            default => throw new \DomainException('This operational source type is not supported for automatic posting.'),
        };
    }

    private function fiscalPeriod(Model $source, string $date): FiscalPeriod
    {
        if ($source->getAttribute('fiscal_period_id')) {
            return FiscalPeriod::query()->lockForUpdate()->findOrFail($source->getAttribute('fiscal_period_id'));
        }

        return FiscalPeriod::query()->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->sole();
    }

    /** @return array{string, ?string, string, JournalEntryType, list<array<string, mixed>>} */
    private function paired(mixed $date, ?string $reference, string $description, JournalEntryType $type, PostingSourceType $rule, string $amount, array $dimensions = []): array
    {
        return [$this->date($date), $reference, $description, $type, [
            $this->component($rule, 'debit', $amount, $description, $dimensions),
            $this->component($rule, 'credit', $amount, $description, $dimensions),
        ]];
    }

    private function component(PostingSourceType $rule, string $side, string $amount, string $description, array $dimensions = []): array
    {
        return compact('rule', 'side', 'amount', 'description', 'dimensions');
    }

    private function date(mixed $date): string
    {
        return Carbon::parse($date)->toDateString();
    }

    private function status(Model $source): string
    {
        $status = $source->getAttribute('status');

        return $status instanceof BackedEnum ? (string) $status->value : (string) $status;
    }

    private function salesInvoiceComponents(SalesInvoice $source): array
    {
        $components = [
            $this->component(PostingSourceType::Sale, 'debit', (string) $source->net_sales_amount, 'Accounts receivable'),
            $this->component(PostingSourceType::Sale, 'credit', (string) $source->gross_amount, 'Gross sales'),
            $this->component(PostingSourceType::SalesDiscount, 'debit', (string) $source->discount_amount, 'Sales discounts'),
        ];

        return [$this->date($source->invoice_date), $source->invoice_number, 'Sales invoice', JournalEntryType::Sales, $components];
    }

    private function customerPaymentComponents(CustomerPayment $source): array
    {
        $components = [
            $this->component(PostingSourceType::CustomerCollection, 'debit', (string) $source->net_cash_received, 'Cash received'),
            $this->component(PostingSourceType::CustomerCollection, 'credit', (string) $source->gross_settlement_amount, 'Accounts receivable settled'),
            $this->component(PostingSourceType::CustomerWithholding, 'debit', (string) $source->withholding_amount, 'Creditable withholding tax'),
            $this->component(PostingSourceType::SalesDiscount, 'debit', (string) $source->other_deductions, 'Other customer deductions'),
        ];

        return [$this->date($source->payment_date), $source->payment_number, 'Customer payment', JournalEntryType::Collection, $components];
    }

    private function supplierInvoiceComponents(SupplierInvoice $source): array
    {
        $debit = bcadd(bcadd((string) $source->gross_purchase_amount, (string) $source->freight_amount, 4), (string) $source->other_charges_amount, 4);
        $components = [
            $this->component(PostingSourceType::Purchase, 'debit', $debit, 'Gross purchases and charges'),
            $this->component(PostingSourceType::Purchase, 'credit', (string) $source->total_payable, 'Accounts payable'),
            $this->component(PostingSourceType::SupplierWithholding, 'credit', (string) $source->withholding_expected_amount, 'Withholding tax payable'),
            $this->component(PostingSourceType::PurchaseDiscount, 'credit', (string) $source->discount_amount, 'Purchase discounts'),
        ];

        return [$this->date($source->invoice_date), $source->internal_number, 'Supplier invoice', JournalEntryType::Purchase, $components];
    }

    private function supplierPaymentComponents(SupplierPayment $source): array
    {
        $components = [
            $this->component(PostingSourceType::SupplierPayment, 'debit', (string) $source->gross_settlement_amount, 'Accounts payable settled'),
            $this->component(PostingSourceType::SupplierPayment, 'credit', (string) $source->net_cash_paid, 'Cash paid'),
            $this->component(PostingSourceType::SupplierWithholding, 'credit', (string) $source->withholding_amount, 'Withholding tax payable'),
            $this->component(PostingSourceType::PurchaseDiscount, 'credit', (string) $source->other_deductions, 'Other supplier deductions'),
        ];

        return [$this->date($source->payment_date), $source->payment_number, 'Supplier payment', JournalEntryType::SupplierPayment, $components];
    }

    private function expenseComponents(Expense $source): array
    {
        $dimensions = ['expense_category' => $source->expense_category];
        $components = [
            $this->component(PostingSourceType::OperatingExpense, 'debit', (string) $source->gross_amount, 'Operating expense', $dimensions),
            $this->component(PostingSourceType::OperatingExpense, 'credit', (string) $source->net_cash_paid, 'Cash paid', $dimensions),
            $this->component(PostingSourceType::SupplierWithholding, 'credit', (string) $source->withholding_amount, 'Withholding tax payable'),
            $this->component(PostingSourceType::PurchaseDiscount, 'credit', (string) $source->other_deductions, 'Other deductions'),
        ];

        return [$this->date($source->expense_date), $source->expense_number, 'Operating expense', JournalEntryType::Expense, $components];
    }

    private function transferComponents(FundTransfer $source): array
    {
        $components = [
            $this->component(PostingSourceType::Transfer, 'debit', $source->amount, 'Destination account', ['financial_account_id' => $source->destination_financial_account_id]),
            $this->component(PostingSourceType::Transfer, 'credit', $source->sourceCashOut(), 'Source account', ['financial_account_id' => $source->source_financial_account_id]),
            $this->component(PostingSourceType::BankCharge, 'debit', $source->transfer_fee, 'Transfer fee', ['financial_account_id' => $source->source_financial_account_id]),
        ];

        return [$this->date($source->transfer_date), $source->transfer_number, 'Fund transfer', JournalEntryType::Transfer, $components];
    }

    private function inventoryComponents(Delivery|ReceivingRecord|InventoryAdjustment|InventoryTransfer $source, string $foreignKey, PostingSourceType $rule, JournalEntryType $type, mixed $date, ?string $reference, string $description): array
    {
        $lineIds = $source->lines()->pluck('id');
        $movements = InventoryMovement::query()->with('product')->whereIn($foreignKey, $lineIds)->whereNull('reversal_of_id')->get();
        $components = [];
        foreach ($movements as $movement) {
            $amount = bccomp($movement->total_cost, '0', 4) < 0 ? bcmul($movement->total_cost, '-1', 4) : $movement->total_cost;
            $dimensions = ['product_category_id' => $movement->product->category_id, 'warehouse_id' => $movement->warehouse_id];
            foreach (['debit', 'credit'] as $side) {
                $components[] = $this->component($rule, $side, $amount, $description, $dimensions) + [
                    'product_id' => $movement->product_service_id,
                    'source_line_type' => 'inventory_movement',
                    'source_line_id' => $movement->id,
                ];
            }
        }

        return [$this->date($date), $reference, $description, $type, $components];
    }

    private function physicalCountComponents(PhysicalCount $source): array
    {
        $lineIds = $source->lines()->pluck('id');
        $movements = InventoryMovement::query()->with('product')->whereIn('physical_count_line_id', $lineIds)->whereNull('reversal_of_id')->get();
        $components = [];
        foreach ($movements as $movement) {
            $gain = bccomp($movement->total_cost, '0', 4) > 0;
            $amount = $gain ? $movement->total_cost : bcmul($movement->total_cost, '-1', 4);
            $rule = $gain ? PostingSourceType::PhysicalCountGain : PostingSourceType::PhysicalCountLoss;
            $dimensions = ['product_category_id' => $movement->product->category_id, 'warehouse_id' => $movement->warehouse_id];
            foreach (['debit', 'credit'] as $side) {
                $components[] = $this->component($rule, $side, $amount, 'Physical-count variance', $dimensions);
            }
        }

        return [$this->date($source->count_date), $source->count_number, 'Physical-count variance', JournalEntryType::Adjustment, $components];
    }
}
