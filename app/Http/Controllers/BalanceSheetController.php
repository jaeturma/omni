<?php

namespace App\Http\Controllers;

use App\Http\Requests\BalanceSheetRequest;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Reports\BalanceSheetReport;
use App\Services\FinancialReportOutput;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheetController extends Controller
{
    public function index(BalanceSheetRequest $request, BalanceSheetReport $report): View
    {
        $filters = $request->validated();

        return view('balance-sheet.index', $this->viewData($filters) + $report->generate($filters));
    }

    public function print(BalanceSheetRequest $request, BalanceSheetReport $report, FinancialReportOutput $output): View
    {
        $filters = $request->validated();

        return view('balance-sheet.print', [
            'filters' => $filters,
            'reportMetadata' => $output->metadata($request->user(), 'Balance Sheet', $filters, 'Accrual basis · Posted general ledger'),
        ] + $report->generate($filters));
    }

    public function drilldown(BalanceSheetRequest $request, Account $account, BalanceSheetReport $report, FinancialReportOutput $output): View
    {
        $filters = $request->validated();

        $drilldown = $report->drilldown($filters, $account);

        return view('balance-sheet.drilldown', [
            'filters' => $filters,
            'account' => $account,
            'rowLinks' => $output->drilldownLinks($drilldown['rows'], $request->user()),
        ] + $drilldown);
    }

    public function export(BalanceSheetRequest $request, BalanceSheetReport $report, FinancialReportOutput $output): StreamedResponse
    {
        $filters = $request->validated();
        $statement = $report->generate($filters);
        $metadata = $output->metadata($request->user(), 'Balance Sheet', $filters, 'Accrual basis · Posted general ledger');

        return response()->streamDownload(function () use ($filters, $statement, $metadata, $output): void {
            $stream = fopen('php://output', 'w');
            $output->writeCsvMetadata($stream, $metadata, $filters);
            fputcsv($stream, ['Section', 'Account Code', 'Account Name', 'Amount']);
            foreach ($statement['sections'] as $section) {
                foreach ($section['rows'] as $row) {
                    fputcsv($stream, [$section['label'], $row['account']->code, $row['account']->name, $row['amount']]);
                }
                fputcsv($stream, [$section['label'].' Total', null, null, $section['total']]);
            }
            fputcsv($stream, []);
            foreach ([
                'total_assets', 'total_liabilities', 'total_equity', 'liabilities_and_equity', 'difference',
            ] as $key) {
                fputcsv($stream, [str($key)->headline()->toString(), null, null, $statement['summary'][$key]]);
            }
            fputcsv($stream, ['Final Ready', null, null, $statement['final_ready'] ? 'Yes' : 'No']);
            fclose($stream);
        }, $output->filename('balance-sheet', $filters), ['Content-Type' => 'text/csv']);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function viewData(array $filters): array
    {
        return [
            'filters' => $filters,
            'periods' => FiscalPeriod::query()->with('fiscalYear:id,name')
                ->latest('starts_on')
                ->get(['id', 'fiscal_year_id', 'name', 'starts_on', 'ends_on']),
        ];
    }
}
