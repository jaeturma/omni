<?php

namespace App\Http\Controllers;

use App\Enums\AccountingSourceType;
use App\Http\Requests\LedgerReportRequest;
use App\Models\Account;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\ProductService;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Reports\GeneralLedgerReport;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralLedgerController extends Controller
{
    public function journal(LedgerReportRequest $request, GeneralLedgerReport $report): View
    {
        $filters = $request->validated();

        return view('ledger-reports.journal', $this->viewData($filters) + ['rows' => $report->journal($filters)]);
    }

    public function ledger(LedgerReportRequest $request, GeneralLedgerReport $report): View
    {
        $filters = $request->validated();

        return view('ledger-reports.ledger', $this->viewData($filters) + $report->ledger($filters));
    }

    public function activity(LedgerReportRequest $request, GeneralLedgerReport $report): View
    {
        $filters = $request->validated();

        return view('ledger-reports.activity', $this->viewData($filters) + $report->ledger($filters));
    }

    public function print(LedgerReportRequest $request, GeneralLedgerReport $report): View
    {
        $filters = $request->validated();
        $type = $request->routeIs('general-journal.*') ? 'journal' : 'ledger';
        $data = $type === 'journal' ? ['rows' => $report->journal($filters, false)] : $report->ledger($filters, false);

        return view('ledger-reports.print', compact('filters', 'type') + $data);
    }

    public function export(LedgerReportRequest $request, GeneralLedgerReport $report): StreamedResponse
    {
        $filters = $request->validated();
        $journal = $request->routeIs('general-journal.*');

        return response()->streamDownload(function () use ($report, $filters, $journal): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $journal
                ? ['Date', 'Journal Number', 'Type', 'Source Type', 'Source ID', 'Reference', 'Description', 'Debit', 'Credit', 'Status']
                : ['Date', 'Account', 'Journal Number', 'Source Type', 'Source ID', 'Reference', 'Description', 'Customer', 'Supplier', 'Financial Account', 'Product', 'Warehouse', 'Debit', 'Credit', 'Running Balance']);
            if ($journal) {
                foreach ($report->journalLazy($filters) as $row) {
                    fputcsv($stream, [
                        $row->journal_date->toDateString(), $row->journal_number, $row->journal_type->value,
                        $row->source_type->value, $row->source_id, $row->reference_number, $row->description,
                        $row->total_debit, $row->total_credit, $row->status->value,
                    ]);
                }
            } else {
                foreach ($report->ledgerLazy($filters) as $row) {
                    fputcsv($stream, [
                        $row->journalEntry->journal_date->toDateString(), $row->account->code,
                        $row->journalEntry->journal_number, $row->journalEntry->source_type->value,
                        $row->journalEntry->source_id, $row->journalEntry->reference_number,
                        $row->description ?? $row->journalEntry->description, $row->customer?->name,
                        $row->supplier?->name, $row->financialAccount?->code, $row->product?->sku,
                        $row->warehouse?->code, $row->debit, $row->credit, $row->running_balance,
                    ]);
                }
            }
            fclose($stream);
        }, ($journal ? 'general-journal-' : 'general-ledger-').$filters['end_date'].'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function viewData(array $filters): array
    {
        return [
            'filters' => $filters,
            'accounts' => Account::query()->ordered()->get(['id', 'code', 'name', 'is_header']),
            'sourceTypes' => AccountingSourceType::cases(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'financialAccounts' => FinancialAccount::query()->orderBy('code')->get(['id', 'code', 'name']),
            'products' => ProductService::query()->orderBy('name')->get(['id', 'sku', 'name']),
            'warehouses' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name']),
        ];
    }
}
