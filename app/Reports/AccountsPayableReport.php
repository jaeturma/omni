<?php

namespace App\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPaymentAllocationStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AccountsPayableReport
{
    public const BUCKETS = ['current', '1-30', '31-60', '61-90', 'over-90'];

    public function detailPaginator(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $asOf = Carbon::parse($filters['as_of']);
        $paginator = $this->openInvoiceQuery($filters, $asOf)->paginate($perPage)->withQueryString();
        $paginator->through(fn (SupplierInvoice $invoice): array => $this->invoiceRow($invoice, $asOf));

        return $paginator;
    }

    public function detailCollection(array $filters): Collection
    {
        $asOf = Carbon::parse($filters['as_of']);

        return $this->openInvoiceQuery($filters, $asOf)->get()->map(fn (SupplierInvoice $invoice): array => $this->invoiceRow($invoice, $asOf));
    }

    public function summary(Collection $rows): Collection
    {
        return $rows->groupBy(fn (array $row): int => $row['invoice']->supplier_id)->map(function (Collection $supplierRows): array {
            $first = $supplierRows->first();
            $totals = array_fill_keys([...self::BUCKETS, 'total'], '0.0000');
            foreach ($supplierRows as $row) {
                $totals[$row['bucket']] = bcadd($totals[$row['bucket']], $row['balance'], 4);
                $totals['total'] = bcadd($totals['total'], $row['balance'], 4);
            }

            return ['supplier' => $first['invoice']->supplier, ...$totals];
        })->values()->toBase();
    }

    public function unappliedPaginator(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $asOf = Carbon::parse($filters['as_of']);
        $allocation = $this->paymentAllocationSubquery($asOf);
        $query = SupplierPayment::query()->with('supplier:id,name')->addSelect(['allocated_as_of' => $allocation])
            ->whereDate('payment_date', '<=', $asOf)->whereNotIn('status', [SupplierPaymentStatus::Draft, SupplierPaymentStatus::Voided])
            ->where('gross_settlement_amount', '>', $allocation);
        $this->applySupplierFilter($query, $filters);
        $paginator = $query->latest('payment_date')->paginate($perPage)->withQueryString();
        $paginator->through(fn (SupplierPayment $payment): array => ['payment' => $payment,
            'unapplied' => bcsub($payment->gross_settlement_amount, bcadd('0', (string) $payment->getAttribute('allocated_as_of'), 4), 4)]);

        return $paginator;
    }

    public function expensePaginator(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $asOf = Carbon::parse($filters['as_of']);
        $query = Expense::query()->with('supplier:id,name')->whereDate('expense_date', '<=', $asOf)
            ->where(function (Builder $query) use ($asOf): void {
                $query->whereIn('status', [ExpenseStatus::Approved, ExpenseStatus::Reimbursable])
                    ->orWhere(fn (Builder $paid): Builder => $paid->where('status', ExpenseStatus::Paid)->whereDate('paid_at', '>', $asOf));
            });
        $this->applySupplierFilter($query, $filters);
        $paginator = $query->oldest('expense_date')->paginate($perPage)->withQueryString();
        $paginator->through(fn (Expense $expense): array => ['expense' => $expense, 'payable' => bcsub(bcsub($expense->gross_amount, $expense->withholding_amount, 4), $expense->other_deductions, 4)]);

        return $paginator;
    }

    public function statement(Supplier $supplier, Carbon $asOf): array
    {
        $filters = ['as_of' => $asOf->toDateString(), 'supplier_id' => $supplier->id];
        $invoices = $this->invoiceQuery($filters, $asOf)->get()->map(fn (SupplierInvoice $invoice): array => $this->invoiceRow($invoice, $asOf));
        $advances = $this->unappliedCollection($supplier, $asOf);
        $invoiceBalance = $invoices->reduce(fn (string $total, array $row): string => bcadd($total, $row['balance'], 4), '0.0000');
        $advanceBalance = $advances->reduce(fn (string $total, array $row): string => bcadd($total, $row['unapplied'], 4), '0.0000');

        return compact('invoices', 'advances') + ['balance' => bcsub($invoiceBalance, $advanceBalance, 4)];
    }

    /** @return Builder<SupplierInvoice> */
    private function openInvoiceQuery(array $filters, Carbon $asOf): Builder
    {
        $query = $this->invoiceQuery($filters, $asOf);
        $allocation = $this->invoiceAllocationSubquery($asOf);
        $query->where('total_payable', '>', $allocation);
        if (($filters['state'] ?? null) === 'partial') {
            $query->whereExists($this->invoiceAllocationExistsSubquery($asOf));
        } elseif (($filters['state'] ?? null) === 'overdue') {
            $query->whereDate('due_date', '<', $asOf);
        }

        return $query;
    }

    /** @return Builder<SupplierInvoice> */
    private function invoiceQuery(array $filters, Carbon $asOf): Builder
    {
        $allocation = $this->invoiceAllocationSubquery($asOf);
        $query = SupplierInvoice::query()->with('supplier:id,name')->addSelect(['allocated_as_of' => $allocation])
            ->whereDate('invoice_date', '<=', $asOf)->whereNotIn('status', [SupplierInvoiceStatus::Draft, SupplierInvoiceStatus::Voided]);
        $this->applySupplierFilter($query, $filters);
        if ($bucket = $filters['bucket'] ?? null) {
            $this->applyBucket($query, $bucket, $asOf);
        }

        return $query->oldest('due_date')->oldest('id');
    }

    private function invoiceAllocationSubquery(Carbon $asOf): Builder
    {
        return SupplierPaymentAllocation::query()->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('supplier_invoice_id', (new SupplierInvoice)->qualifyColumn('id'))
            ->where('status', SupplierPaymentAllocationStatus::Active)->whereDate('allocated_at', '<=', $asOf)
            ->whereIn('supplier_payment_id', SupplierPayment::query()->select('id')->whereNotIn('status', [SupplierPaymentStatus::Draft, SupplierPaymentStatus::Voided])->whereDate('payment_date', '<=', $asOf));
    }

    private function invoiceAllocationExistsSubquery(Carbon $asOf): Builder
    {
        return SupplierPaymentAllocation::query()->selectRaw('1')->whereColumn('supplier_invoice_id', (new SupplierInvoice)->qualifyColumn('id'))
            ->where('status', SupplierPaymentAllocationStatus::Active)->whereDate('allocated_at', '<=', $asOf);
    }

    private function paymentAllocationSubquery(Carbon $asOf): Builder
    {
        return SupplierPaymentAllocation::query()->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('supplier_payment_id', (new SupplierPayment)->qualifyColumn('id'))
            ->where('status', SupplierPaymentAllocationStatus::Active)->whereDate('allocated_at', '<=', $asOf);
    }

    private function applySupplierFilter(Builder $query, array $filters): void
    {
        if ($supplierId = $filters['supplier_id'] ?? null) {
            $query->where('supplier_id', $supplierId);
        }
    }

    private function applyBucket(Builder $query, string $bucket, Carbon $asOf): void
    {
        match ($bucket) {
            'current' => $query->whereDate('due_date', '>=', $asOf),
            '1-30' => $query->whereBetween('due_date', [$asOf->copy()->subDays(30), $asOf->copy()->subDay()]),
            '31-60' => $query->whereBetween('due_date', [$asOf->copy()->subDays(60), $asOf->copy()->subDays(31)]),
            '61-90' => $query->whereBetween('due_date', [$asOf->copy()->subDays(90), $asOf->copy()->subDays(61)]),
            'over-90' => $query->whereDate('due_date', '<', $asOf->copy()->subDays(90)),
            default => $query,
        };
    }

    private function invoiceRow(SupplierInvoice $invoice, Carbon $asOf): array
    {
        $allocated = bcadd('0', (string) $invoice->getAttribute('allocated_as_of'), 4);
        $balance = bcsub($invoice->total_payable, $allocated, 4);
        $daysOverdue = $invoice->due_date->gte($asOf) ? 0 : (int) $invoice->due_date->diffInDays($asOf);
        $bucket = match (true) {
            $daysOverdue === 0 => 'current', $daysOverdue <= 30 => '1-30', $daysOverdue <= 60 => '31-60', $daysOverdue <= 90 => '61-90', default => 'over-90'
        };

        return compact('invoice', 'allocated', 'balance', 'daysOverdue', 'bucket');
    }

    private function unappliedCollection(Supplier $supplier, Carbon $asOf): Collection
    {
        $allocation = $this->paymentAllocationSubquery($asOf);

        return SupplierPayment::whereBelongsTo($supplier)->whereDate('payment_date', '<=', $asOf)
            ->whereNotIn('status', [SupplierPaymentStatus::Draft, SupplierPaymentStatus::Voided])->addSelect(['allocated_as_of' => $allocation])->get()
            ->map(fn (SupplierPayment $payment): array => ['payment' => $payment, 'unapplied' => bcsub($payment->gross_settlement_amount, bcadd('0', (string) $payment->getAttribute('allocated_as_of'), 4), 4)])
            ->filter(fn (array $row): bool => bccomp($row['unapplied'], '0', 4) === 1)->values();
    }
}
