<?php

namespace App\Services;

use App\Enums\AccountingSourceType;
use App\Models\BusinessProfile;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class FinancialReportOutput
{
    /** @param array<string, mixed> $filters
     * @return array{business_name: string, report_name: string, period: string, basis: string, generated_at: string, generated_by: string, confidential_label: string|null}
     */
    public function metadata(User $user, string $reportName, array $filters, string $basis): array
    {
        $businessName = BusinessProfile::query()->active()->value('registered_business_name')
            ?? config('app.name', 'Omni Mini-ERP');

        return [
            'business_name' => (string) $businessName,
            'report_name' => $reportName,
            'period' => $this->period($filters),
            'basis' => $basis,
            'generated_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s T'),
            'generated_by' => $user->name,
            'confidential_label' => filled(config('financial_reports.confidential_label'))
                ? (string) config('financial_reports.confidential_label') : null,
        ];
    }

    /** @param resource $stream
     * @param  array<string, string|null>  $metadata
     * @param  array<string, mixed>  $filters
     */
    public function writeCsvMetadata($stream, array $metadata, array $filters): void
    {
        foreach ($metadata as $label => $value) {
            if ($value !== null) {
                fputcsv($stream, [Str::headline($label), $value]);
            }
        }
        foreach ($filters as $parameter => $value) {
            fputcsv($stream, [Str::headline($parameter), is_bool($value) ? ($value ? 'Yes' : 'No') : $value]);
        }
        fputcsv($stream, []);
    }

    /** @param array<string, mixed> $filters */
    public function filename(string $reportSlug, array $filters, string $extension = 'csv'): string
    {
        $period = $filters['end_date'] ?? $filters['as_of'] ?? $filters['current_end_date'] ?? now()->toDateString();

        return Str::slug($reportSlug).'-'.$period.'-'.now()->format('Ymd-His').'.'.$extension;
    }

    /** @return array<int, array{journal_url: string|null, source_url: string|null, source_label: string|null}> */
    public function drilldownLinks(LengthAwarePaginator $rows, User $user): array
    {
        $links = [];
        foreach ($rows->items() as $line) {
            $entry = $line->journalEntry;
            $links[(int) $line->getKey()] = [
                'journal_url' => $user->can('journals.view') ? route('journal-entries.show', $entry) : null,
                ...$this->sourceLink($entry, $user),
            ];
        }

        return $links;
    }

    /** @return array{source_url: string|null, source_label: string|null} */
    private function sourceLink(JournalEntry $entry, User $user): array
    {
        if (! $user->can('financial-reports.view-source') || $entry->source_id === null) {
            return ['source_url' => null, 'source_label' => null];
        }

        $definition = match ($entry->source_type) {
            AccountingSourceType::SalesInvoice => ['sales-invoices.show', 'sales-invoices.view'],
            AccountingSourceType::CustomerPayment => ['customer-payments.show', 'customer-payments.view'],
            AccountingSourceType::SupplierInvoice => ['supplier-invoices.show', 'supplier-invoices.view'],
            AccountingSourceType::SupplierPayment => ['supplier-payments.show', 'supplier-payments.view'],
            AccountingSourceType::Expense => ['expenses.show', 'expenses.view'],
            AccountingSourceType::CashReceipt => ['cash-receipts.show', 'cash-receipts.view'],
            AccountingSourceType::CashDisbursement => ['cash-disbursements.show', 'cash-disbursements.view'],
            default => null,
        };
        if ($definition === null || ! Route::has($definition[0]) || ! $user->can($definition[1])) {
            return ['source_url' => null, 'source_label' => null];
        }

        return [
            'source_url' => route($definition[0], $entry->source_id),
            'source_label' => $entry->reference_number ?: Str::headline($entry->source_type->value),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function period(array $filters): string
    {
        if (isset($filters['current_start_date'], $filters['current_end_date'], $filters['comparison_start_date'], $filters['comparison_end_date'])) {
            return "{$filters['current_start_date']} to {$filters['current_end_date']} compared with {$filters['comparison_start_date']} to {$filters['comparison_end_date']}";
        }
        if (isset($filters['start_date'], $filters['end_date'])) {
            return "{$filters['start_date']} to {$filters['end_date']}";
        }

        return 'As of '.($filters['as_of'] ?? CarbonImmutable::now()->toDateString());
    }
}
