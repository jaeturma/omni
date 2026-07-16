<?php

namespace App\Reports;

use App\Enums\CustomerPaymentStatus;
use App\Enums\PaymentAllocationStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentAllocation;
use App\Models\SalesInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReceivablesReport
{
    public const BUCKETS = ['current', '1-30', '31-60', '61-90', 'over-90'];

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array{invoice: SalesInvoice, allocated: numeric-string, balance: numeric-string, daysOverdue: int, bucket: string}>
     */
    public function detailPaginator(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $asOf = Carbon::parse($filters['as_of']);
        $paginator = $this->openInvoiceQuery($filters, $asOf)->paginate($perPage)->withQueryString();
        $paginator->through(fn (SalesInvoice $invoice): array => $this->invoiceRow($invoice, $asOf));

        return $paginator;
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, array{invoice: SalesInvoice, allocated: numeric-string, balance: numeric-string, daysOverdue: int, bucket: string}>
     */
    public function detailCollection(array $filters): Collection
    {
        $asOf = Carbon::parse($filters['as_of']);

        return $this->openInvoiceQuery($filters, $asOf)->get()->map(fn (SalesInvoice $invoice): array => $this->invoiceRow($invoice, $asOf));
    }

    /** @param Collection<int, array{invoice: SalesInvoice, allocated: numeric-string, balance: numeric-string, daysOverdue: int, bucket: string}> $rows */
    public function summary(Collection $rows): Collection
    {
        return $rows->groupBy(fn (array $row): int => $row['invoice']->customer_id)->map(function (Collection $customerRows): array {
            $first = $customerRows->first();
            $totals = ['current' => '0.0000', '1-30' => '0.0000', '31-60' => '0.0000', '61-90' => '0.0000', 'over-90' => '0.0000', 'total' => '0.0000'];
            foreach ($customerRows as $row) {
                $totals[$row['bucket']] = bcadd($totals[$row['bucket']], $row['balance'], 4);
                $totals['total'] = bcadd($totals['total'], $row['balance'], 4);
            }

            return ['customer' => $first['invoice']->customer, 'current' => $totals['current'], '1-30' => $totals['1-30'],
                '31-60' => $totals['31-60'], '61-90' => $totals['61-90'], 'over-90' => $totals['over-90'], 'total' => $totals['total']];
        })->values()->toBase();
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array{payment: CustomerPayment, unapplied: numeric-string}>
     */
    public function unappliedPaginator(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $asOf = Carbon::parse($filters['as_of']);
        $allocation = $this->paymentAllocationSubquery($asOf);
        $query = CustomerPayment::query()->select(['id', 'customer_id', 'payment_number', 'payment_date', 'gross_settlement_amount', 'status'])
            ->with('customer:id,name,type')->addSelect(['allocated_as_of' => $allocation])
            ->whereDate('payment_date', '<=', $asOf)->whereNotIn('status', [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided])
            ->where('gross_settlement_amount', '>', $allocation);
        $this->applyCustomerFilters($query, $filters);

        $paginator = $query->latest('payment_date')->paginate($perPage)->withQueryString();
        $paginator->through(function (CustomerPayment $payment): array {
            $allocated = bcadd('0', (string) $payment->getAttribute('allocated_as_of'), 4);

            return ['payment' => $payment, 'unapplied' => bcsub($payment->gross_settlement_amount, $allocated, 4)];
        });

        return $paginator;
    }

    /** @return array{invoices: Collection<int, array{invoice: SalesInvoice, allocated: numeric-string, balance: numeric-string, daysOverdue: int, bucket: string}>, allocations: Collection<int, PaymentAllocation>, payments: Collection<int, array{payment: CustomerPayment, unapplied: numeric-string}>, balance: numeric-string} */
    public function statement(Customer $customer, Carbon $asOf): array
    {
        $filters = ['as_of' => $asOf->toDateString(), 'customer_id' => $customer->id];
        $invoices = $this->invoiceQuery($filters, $asOf)->get()->map(fn (SalesInvoice $invoice): array => $this->invoiceRow($invoice, $asOf));
        $invoiceIds = $invoices->pluck('invoice.id');
        $allocations = PaymentAllocation::with(['customerPayment:id,payment_number,payment_date'])
            ->whereIn('sales_invoice_id', $invoiceIds)->where('status', PaymentAllocationStatus::Active)
            ->whereDate('allocated_at', '<=', $asOf)->oldest('allocated_at')->get();
        $payments = $this->unappliedCollection($customer, $asOf);
        $balance = $invoices->reduce(fn (string $total, array $row): string => bcadd($total, $row['balance'], 4), '0.0000');

        return compact('invoices', 'allocations', 'payments', 'balance');
    }

    /** @param array<string, mixed> $filters
     * @return Builder<SalesInvoice>
     */
    private function openInvoiceQuery(array $filters, Carbon $asOf): Builder
    {
        $query = $this->invoiceQuery($filters, $asOf);
        $allocation = $this->invoiceAllocationSubquery($asOf);
        $query->where('total_receivable', '>', $allocation);
        if (($filters['state'] ?? null) === 'partial') {
            $query->whereExists($this->invoiceAllocationExistsSubquery($asOf));
        } elseif (($filters['state'] ?? null) === 'overdue') {
            $query->whereDate('due_date', '<', $asOf);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return Builder<SalesInvoice>
     */
    private function invoiceQuery(array $filters, Carbon $asOf): Builder
    {
        $allocation = $this->invoiceAllocationSubquery($asOf);
        $query = SalesInvoice::query()->select(['id', 'customer_id', 'invoice_number', 'invoice_date', 'due_date', 'total_receivable', 'status'])
            ->with('customer:id,name,type')->addSelect(['allocated_as_of' => $allocation])
            ->whereDate('invoice_date', '<=', $asOf)->whereNotIn('status', [SalesInvoiceStatus::Draft, SalesInvoiceStatus::Voided]);
        $this->applyCustomerFilters($query, $filters);
        if ($bucket = $filters['bucket'] ?? null) {
            $this->applyBucket($query, $bucket, $asOf);
        }

        return $query->oldest('due_date')->oldest('id');
    }

    private function invoiceAllocationSubquery(Carbon $asOf): Builder
    {
        return PaymentAllocation::query()->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('sales_invoice_id', (new SalesInvoice)->qualifyColumn('id'))
            ->where('status', PaymentAllocationStatus::Active)->whereDate('allocated_at', '<=', $asOf)
            ->whereIn('customer_payment_id', CustomerPayment::query()->select('id')->whereNotIn('status', [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided])->whereDate('payment_date', '<=', $asOf));
    }

    /** @return Builder<PaymentAllocation> */
    private function invoiceAllocationExistsSubquery(Carbon $asOf): Builder
    {
        return PaymentAllocation::query()->selectRaw('1')
            ->whereColumn('sales_invoice_id', (new SalesInvoice)->qualifyColumn('id'))
            ->where('status', PaymentAllocationStatus::Active)->whereDate('allocated_at', '<=', $asOf)
            ->whereIn('customer_payment_id', CustomerPayment::query()->select('id')->whereNotIn('status', [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided])->whereDate('payment_date', '<=', $asOf));
    }

    private function paymentAllocationSubquery(Carbon $asOf): Builder
    {
        return PaymentAllocation::query()->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('customer_payment_id', (new CustomerPayment)->qualifyColumn('id'))
            ->where('status', PaymentAllocationStatus::Active)->whereDate('allocated_at', '<=', $asOf);
    }

    /** @param Builder<SalesInvoice>|Builder<CustomerPayment> $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCustomerFilters(Builder $query, array $filters): void
    {
        if ($customerId = $filters['customer_id'] ?? null) {
            $query->where('customer_id', $customerId);
        }
        if ($type = $filters['customer_type'] ?? null) {
            $query->whereIn('customer_id', Customer::query()->select('id')->where('type', $type));
        }
    }

    /** @param Builder<SalesInvoice> $query */
    private function applyBucket(Builder $query, string $bucket, Carbon $asOf): void
    {
        match ($bucket) {
            'current' => $query->whereDate('due_date', '>=', $asOf),
            '1-30' => $query->whereBetween('due_date', [$asOf->copy()->subDays(30)->toDateString(), $asOf->copy()->subDay()->toDateString()]),
            '31-60' => $query->whereBetween('due_date', [$asOf->copy()->subDays(60)->toDateString(), $asOf->copy()->subDays(31)->toDateString()]),
            '61-90' => $query->whereBetween('due_date', [$asOf->copy()->subDays(90)->toDateString(), $asOf->copy()->subDays(61)->toDateString()]),
            'over-90' => $query->whereDate('due_date', '<', $asOf->copy()->subDays(90)),
            default => $query,
        };
    }

    /** @return array{invoice: SalesInvoice, allocated: numeric-string, balance: numeric-string, daysOverdue: int, bucket: string} */
    private function invoiceRow(SalesInvoice $invoice, Carbon $asOf): array
    {
        $allocated = bcadd('0', (string) $invoice->getAttribute('allocated_as_of'), 4);
        $balance = bcsub((string) $invoice->total_receivable, $allocated, 4);
        $daysOverdue = $invoice->due_date->gte($asOf) ? 0 : (int) $invoice->due_date->diffInDays($asOf);
        $bucket = match (true) {
            $daysOverdue === 0 => 'current', $daysOverdue <= 30 => '1-30', $daysOverdue <= 60 => '31-60',
            $daysOverdue <= 90 => '61-90', default => 'over-90',
        };

        return compact('invoice', 'allocated', 'balance', 'daysOverdue', 'bucket');
    }

    /** @return Collection<int, array{payment: CustomerPayment, unapplied: numeric-string}> */
    private function unappliedCollection(Customer $customer, Carbon $asOf): Collection
    {
        $allocation = $this->paymentAllocationSubquery($asOf);

        return CustomerPayment::whereBelongsTo($customer)->whereDate('payment_date', '<=', $asOf)
            ->whereNotIn('status', [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided])->addSelect(['allocated_as_of' => $allocation])
            ->oldest('payment_date')->get()->map(function (CustomerPayment $payment): array {
                $allocated = bcadd('0', (string) $payment->getAttribute('allocated_as_of'), 4);

                return ['payment' => $payment, 'unapplied' => bcsub($payment->gross_settlement_amount, $allocated, 4)];
            })->filter(fn (array $row): bool => bccomp($row['unapplied'], '0', 4) === 1)->values();
    }
}
