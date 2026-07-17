<?php

namespace App\Reports;

use App\Enums\GovernmentDeductionStatus;
use App\Models\GovernmentDeduction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GovernmentDeductionSummary
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, GovernmentDeduction>
     */
    public function paginator(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)->latest('covered_to')->paginate(25)->withQueryString();
    }

    /** @param array<string, mixed> $filters
     * @return array{gross_sales: numeric-string, deductions: numeric-string, net_after_deductions: numeric-string, by_customer: Collection}
     */
    public function totals(array $filters): array
    {
        $deductions = $this->query($filters)->get();
        $grossSales = $deductions->unique('sales_invoice_id')->reduce(
            fn (string $total, GovernmentDeduction $deduction): string => bcadd($total, $deduction->salesInvoice->gross_amount, 4), '0.0000');
        $totalDeductions = $deductions->reduce(
            fn (string $total, GovernmentDeduction $deduction): string => bcadd($total, $deduction->amount, 4), '0.0000');
        $byCustomer = $deductions->groupBy('customer_id')->map(function (Collection $rows): array {
            $first = $rows->first();
            $gross = $rows->unique('sales_invoice_id')->reduce(
                fn (string $total, GovernmentDeduction $deduction): string => bcadd($total, $deduction->salesInvoice->gross_amount, 4), '0.0000');
            $amount = $rows->reduce(fn (string $total, GovernmentDeduction $deduction): string => bcadd($total, $deduction->amount, 4), '0.0000');

            return ['customer' => $first?->customer, 'gross_sales' => $gross, 'deductions' => $amount, 'net_after_deductions' => bcsub($gross, $amount, 4)];
        })->values()->toBase();

        return ['gross_sales' => $grossSales, 'deductions' => $totalDeductions,
            'net_after_deductions' => bcsub($grossSales, $totalDeductions, 4), 'by_customer' => $byCustomer];
    }

    /** @param array<string, mixed> $filters
     * @return Builder<GovernmentDeduction>
     */
    private function query(array $filters): Builder
    {
        $query = GovernmentDeduction::with(['customer:id,name', 'salesInvoice:id,invoice_number,gross_amount', 'customerPayment:id,payment_number'])
            ->whereYear('covered_to', $filters['year']);
        if ($quarter = $filters['quarter'] ?? null) {
            $query->whereMonth('covered_to', '>=', (($quarter - 1) * 3) + 1)->whereMonth('covered_to', '<=', $quarter * 3);
        }
        if ($customerId = $filters['customer_id'] ?? null) {
            $query->where('customer_id', $customerId);
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', GovernmentDeductionStatus::Voided);
        }
        if ($filters['missing_certificate'] ?? false) {
            $query->where(fn ($builder) => $builder->whereNull('certificate_number')->orWhereNull('certificate_date'));
        }

        return $query;
    }
}
