<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Models\BusinessProfile;
use App\Models\DocumentNumberReservation;
use App\Models\FiscalYear;
use App\Models\GovernmentDeduction;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\SalesInvoice;
use App\Models\SupplierInvoice;
use App\Models\TaxFilingPayment;
use App\Models\TaxPeriod;
use App\Models\User;
use Illuminate\Support\Collection;

class BooksAndSchedules
{
    public const PERMISSIONS = ['books-of-accounts.view', 'books-of-accounts.export', 'tax-schedules.view', 'tax-schedules.export'];

    public const REPORTS = [
        'general_journal' => ['label' => 'General journal', 'group' => 'books'], 'general_ledger' => ['label' => 'General ledger', 'group' => 'books'],
        'cash_receipts' => ['label' => 'Cash receipts book', 'group' => 'books'], 'cash_disbursements' => ['label' => 'Cash disbursements book', 'group' => 'books'],
        'sales' => ['label' => 'Sales book', 'group' => 'books'], 'purchases' => ['label' => 'Purchase book', 'group' => 'books'],
        'expenses' => ['label' => 'Expense book', 'group' => 'books'], 'inventory' => ['label' => 'Inventory / stock ledger', 'group' => 'books'],
        'receivables' => ['label' => 'Accounts receivable schedule', 'group' => 'schedules'], 'payables' => ['label' => 'Accounts payable schedule', 'group' => 'schedules'],
        'withholding_certificates' => ['label' => 'Withholding certificate schedule', 'group' => 'schedules'], 'tax_payments' => ['label' => 'Tax-payment schedule', 'group' => 'schedules'],
        'invoice_sequences' => ['label' => 'Invoice-sequence report', 'group' => 'schedules'], 'annual_inventory' => ['label' => 'Annual inventory listing support schedule', 'group' => 'schedules'],
    ];

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function generate(array $filters, User $user): array
    {
        $filters = $this->resolveRange($filters);
        $report = (string) $filters['report'];
        $rows = match ($report) {
            'general_journal' => $this->journalRows($filters), 'general_ledger' => $this->ledgerRows($filters),
            'cash_receipts' => $this->journalRows($filters, ['cash_receipt', 'customer_payment']),
            'cash_disbursements' => $this->journalRows($filters, ['cash_disbursement', 'supplier_payment']),
            'sales' => $this->journalRows($filters, ['sales_invoice']), 'purchases' => $this->journalRows($filters, ['supplier_invoice']),
            'expenses' => $this->journalRows($filters, ['expense', 'petty_cash_voucher']), 'inventory' => $this->inventoryRows($filters, false),
            'receivables' => $this->receivableRows($filters), 'payables' => $this->payableRows($filters),
            'withholding_certificates' => $this->withholdingRows($filters), 'tax_payments' => $this->taxPaymentRows($filters),
            'invoice_sequences' => $this->sequenceRows($filters), 'annual_inventory' => $this->inventoryRows($filters, true),
            default => throw new \InvalidArgumentException('Unsupported books or schedules report.'),
        };
        $profile = BusinessProfile::query()->with('taxProfile')->active()->first();
        $bookType = $profile?->taxProfile?->registered_books_type;
        $balances = in_array($report, ['cash_receipts', 'cash_disbursements'], true) ? $this->cashBalances($filters) : null;

        return ['report' => $report, 'label' => self::REPORTS[$report]['label'], 'group' => self::REPORTS[$report]['group'], 'filters' => $filters, 'rows' => $rows,
            'headers' => $rows->isEmpty() ? ['Date', 'Reference', 'Description', 'Debit', 'Credit', 'Amount'] : array_keys($rows->first()),
            'totals' => $this->totals($rows), 'business' => $profile, 'book_type' => $bookType,
            'classification' => match ($bookType) {
                'manual' => 'Manual-book support', 'loose_leaf' => 'Loose-leaf draft', 'computerized' => 'Computerized-book export', default => 'Internal review output'
            },
            'configuration_warning' => blank($bookType) ? 'Registered-book configuration is missing. This output is for internal review only.' : null,
            'generated_by' => $user->name, 'generated_at' => now(), 'balances' => $balances,
            'disclaimer' => 'Review support only. This export is not automatically an approved or registered BIR book.',
        ];
    }

    /** @param array<string, mixed> $filters @return array{beginning: string, ending: string} */
    private function cashBalances(array $filters): array
    {
        $balance = function (string $operator, string $date): string {
            $query = JournalEntryLine::query()->whereHas('account', fn ($query) => $query->where('account_type', AccountType::Cash))
                ->whereHas('journalEntry', fn ($query) => $query->where('status', JournalEntryStatus::Posted)->whereDate('journal_date', $operator, $date));

            return bcsub((string) (clone $query)->sum('debit'), (string) (clone $query)->sum('credit'), 4);
        };

        return ['beginning' => $balance('<', (string) $filters['start_date']), 'ending' => $balance('<=', (string) $filters['end_date'])];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function resolveRange(array $filters): array
    {
        if ($filters['tax_period_id'] ?? null) {
            $period = TaxPeriod::query()->findOrFail($filters['tax_period_id']);
            $filters['start_date'] = $period->capture_start->toDateString();
            $filters['end_date'] = $period->period_end->toDateString();
        } elseif ($filters['fiscal_year_id'] ?? null) {
            $year = FiscalYear::query()->findOrFail($filters['fiscal_year_id']);
            $filters['start_date'] = $year->starts_on->toDateString();
            $filters['end_date'] = $year->ends_on->toDateString();
        }

        return $filters;
    }

    /** @param array<string, mixed> $filters @param list<string>|null $sources @return Collection<int, array<string, string>> */
    private function journalRows(array $filters, ?array $sources = null): Collection
    {
        return JournalEntry::query()->where('status', JournalEntryStatus::Posted)->whereBetween('journal_date', [$filters['start_date'], $filters['end_date']])
            ->when($sources, fn ($query) => $query->whereIn('source_type', $sources))->orderBy('journal_date')->orderBy('journal_number')->get()
            ->map(fn (JournalEntry $row): array => ['Date' => $row->journal_date->toDateString(), 'Reference' => $row->journal_number, 'Description' => $row->description, 'Debit' => $row->total_debit, 'Credit' => $row->total_credit]);
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, string>> */
    private function ledgerRows(array $filters): Collection
    {
        return JournalEntryLine::query()->with(['journalEntry:id,journal_number,journal_date,status', 'account:id,code,name'])->whereHas('journalEntry', fn ($query) => $query->where('status', JournalEntryStatus::Posted)->whereBetween('journal_date', [$filters['start_date'], $filters['end_date']]))
            ->get()->sortBy(fn (JournalEntryLine $line): string => $line->journalEntry->journal_date->format('Ymd').'-'.str_pad((string) $line->journal_entry_id, 12, '0', STR_PAD_LEFT).'-'.str_pad((string) $line->line_number, 6, '0', STR_PAD_LEFT))->values()
            ->map(fn (JournalEntryLine $line): array => ['Date' => $line->journalEntry->journal_date->toDateString(), 'Reference' => $line->journalEntry->journal_number, 'Account' => $line->account->code.' '.$line->account->name, 'Description' => $line->description ?? '', 'Debit' => $line->debit, 'Credit' => $line->credit]);
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, string>> */
    private function inventoryRows(array $filters, bool $endingOnly): Collection
    {
        $rows = InventoryMovement::query()->with(['product:id,sku,name', 'warehouse:id,code,name'])->where('status', 'posted')->whereDate('movement_date', '<=', $filters['end_date'])
            ->when(! $endingOnly, fn ($query) => $query->whereDate('movement_date', '>=', $filters['start_date']))->orderBy('movement_date')->orderBy('id')->get();
        if ($endingOnly) {
            $rows = $rows->groupBy(fn (InventoryMovement $row): string => $row->product_service_id.'-'.$row->warehouse_id)->map->last()->values();
        }

        return $rows->map(fn (InventoryMovement $row): array => ['Date' => $row->movement_date->toDateString(), 'Reference' => $row->type->value.'-'.$row->id, 'Product' => $row->product->sku.' '.$row->product->name, 'Warehouse' => $row->warehouse->code, 'Quantity' => $endingOnly ? (string) $row->balance_quantity_after : $row->quantity, 'Unit Cost' => $endingOnly ? (string) $row->balance_average_cost_after : $row->unit_cost, 'Amount' => $endingOnly ? bcmul((string) $row->balance_quantity_after, (string) $row->balance_average_cost_after, 4) : $row->total_cost]);
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, string>> */
    private function receivableRows(array $filters): Collection
    {
        return SalesInvoice::query()->with('customer:id,name')->whereIn('status', ['posted', 'partially_paid', 'paid', 'overdue'])->whereDate('invoice_date', '<=', $filters['end_date'])->orderBy('invoice_date')->orderBy('invoice_number')->get()
            ->map(fn (SalesInvoice $row): array => ['Date' => $row->invoice_date->toDateString(), 'Reference' => $row->invoice_number, 'Customer' => $row->customer->name, 'Amount' => $row->total_receivable, 'Balance' => $row->balance_due]);
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, string>> */
    private function payableRows(array $filters): Collection
    {
        return SupplierInvoice::query()->with('supplier:id,name')->whereIn('status', ['posted', 'partially_paid', 'paid', 'overdue'])->whereDate('invoice_date', '<=', $filters['end_date'])->orderBy('invoice_date')->orderBy('internal_number')->get()
            ->map(fn (SupplierInvoice $row): array => ['Date' => $row->invoice_date->toDateString(), 'Reference' => $row->internal_number, 'Supplier' => $row->supplier->name, 'Amount' => $row->total_payable, 'Balance' => $row->balance_due]);
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, string>> */
    private function withholdingRows(array $filters): Collection
    {
        return GovernmentDeduction::query()->with(['customer:id,name', 'salesInvoice:id,invoice_number'])->where('status', '!=', 'voided')->whereBetween('certificate_date', [$filters['start_date'], $filters['end_date']])->orderBy('certificate_date')->orderBy('certificate_number')->get()
            ->map(fn (GovernmentDeduction $row): array => ['Date' => $row->certificate_date?->toDateString() ?? '', 'Reference' => $row->certificate_type.' '.$row->certificate_number, 'Customer' => $row->customer->name, 'Invoice' => $row->salesInvoice->invoice_number, 'Gross Basis' => $row->gross_basis, 'Rate' => $row->rate, 'Amount' => $row->amount, 'Applied' => $row->appliedAmount(), 'Balance' => $row->remainingAmount()]);
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, string>> */
    private function taxPaymentRows(array $filters): Collection
    {
        return TaxFilingPayment::query()->with('taxFiling:id,bir_form_number,return_reference')
            ->whereBetween('payment_date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('payment_date')->orderBy('id')->get()
            ->map(fn (TaxFilingPayment $row): array => [
                'Date' => $row->payment_date->toDateString(),
                'Reference' => $row->payment_reference,
                'Description' => $row->taxFiling->bir_form_number.' '.$row->taxFiling->return_reference.' via '.$row->payment_channel,
                'Amount' => $row->amount_paid,
            ]);
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, string>> */
    private function sequenceRows(array $filters): Collection
    {
        return DocumentNumberReservation::query()->with(['documentSequence:id,document_type', 'issuer:id,name'])->whereBetween('issued_at', [$filters['start_date'], $filters['end_date'].' 23:59:59'])->orderBy('issued_at')->orderBy('id')->get()
            ->map(fn (DocumentNumberReservation $row): array => ['Date' => $row->issued_at->toDateString(), 'Reference' => $row->document_number, 'Description' => str($row->documentSequence->document_type)->headline()->toString(), 'Sequence Number' => (string) $row->number, 'Generated By' => $row->issuer->name]);
    }

    /** @param Collection<int, array<string, string>> $rows @return array<string, string> */
    private function totals(Collection $rows): array
    {
        $totals = [];
        foreach (['Debit', 'Credit', 'Amount', 'Balance', 'Quantity'] as $field) {
            if ($rows->contains(fn (array $row): bool => array_key_exists($field, $row))) {
                $totals[$field] = $rows->reduce(fn (string $sum, array $row): string => bcadd($sum, $row[$field] ?? '0', 4), '0.0000');
            }
        }

        return $totals;
    }
}
