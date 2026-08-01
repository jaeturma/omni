<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeStatementRequest;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Reports\IncomeStatementReport;
use App\Services\FinancialReportOutput;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeStatementController extends Controller
{
    public function index(IncomeStatementRequest $request, IncomeStatementReport $report): View
    {
        $filters = $request->validated();

        return view('income-statement.index', $this->viewData($filters) + $report->generate($filters));
    }

    public function print(IncomeStatementRequest $request, IncomeStatementReport $report, FinancialReportOutput $output): View
    {
        $filters = $request->validated();

        return view('income-statement.print', [
            'filters' => $filters,
            'reportMetadata' => $output->metadata($request->user(), 'Income Statement', $filters, 'Accrual basis · Posted general ledger'),
        ] + $report->generate($filters));
    }

    public function drilldown(IncomeStatementRequest $request, Account $account, IncomeStatementReport $report, FinancialReportOutput $output): View
    {
        $filters = $request->validated();

        $drilldown = $report->drilldown($filters, $account);

        return view('income-statement.drilldown', [
            'filters' => $filters,
            'account' => $account,
            'rowLinks' => $output->drilldownLinks($drilldown['rows'], $request->user()),
        ] + $drilldown);
    }

    public function export(IncomeStatementRequest $request, IncomeStatementReport $report, FinancialReportOutput $output): StreamedResponse
    {
        $filters = $request->validated();
        $statement = $report->generate($filters);
        $metadata = $output->metadata($request->user(), 'Income Statement', $filters, 'Accrual basis · Posted general ledger');

        return response()->streamDownload(function () use ($filters, $statement, $metadata, $output): void {
            $stream = fopen('php://output', 'w');
            $output->writeCsvMetadata($stream, $metadata, $filters);
            fputcsv($stream, ['Section', 'Account Code', 'Account Name', 'Amount']);
            foreach ($statement['sections'] as $section) {
                if ($section['key'] === 'income_tax' && ! $statement['has_income_tax']) {
                    continue;
                }
                foreach ($section['rows'] as $row) {
                    fputcsv($stream, [$section['label'], $row['account']->code, $row['account']->name, $row['amount']]);
                }
                fputcsv($stream, [$section['label'].' Total', null, null, $section['total']]);
            }
            fputcsv($stream, []);
            foreach ([
                'net_sales', 'gross_profit', 'operating_income', 'net_income_before_tax', 'income_tax', 'net_income_after_tax',
            ] as $key) {
                if ($key !== 'income_tax' || $statement['has_income_tax']) {
                    fputcsv($stream, [str($key)->headline()->toString(), null, null, $statement['summary'][$key]]);
                }
            }
            fclose($stream);
        }, $output->filename('income-statement', $filters), ['Content-Type' => 'text/csv']);
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
