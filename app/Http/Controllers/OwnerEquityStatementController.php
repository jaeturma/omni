<?php

namespace App\Http\Controllers;

use App\Http\Requests\OwnerEquityStatementRequest;
use App\Models\FiscalPeriod;
use App\Reports\OwnerEquityStatementReport;
use App\Services\FinancialReportOutput;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerEquityStatementController extends Controller
{
    public function index(OwnerEquityStatementRequest $request, OwnerEquityStatementReport $report): View
    {
        $filters = $request->validated();

        return view('owner-equity-statement.index', $this->viewData($filters) + $report->generate($filters));
    }

    public function print(OwnerEquityStatementRequest $request, OwnerEquityStatementReport $report, FinancialReportOutput $output): View
    {
        $filters = $request->validated();

        return view('owner-equity-statement.print', [
            'filters' => $filters,
            'reportMetadata' => $output->metadata($request->user(), "Statement of Changes in Owner's Equity", $filters, 'Accrual basis · Posted general ledger'),
        ] + $report->generate($filters));
    }

    public function drilldown(
        OwnerEquityStatementRequest $request,
        string $activity,
        OwnerEquityStatementReport $report,
        FinancialReportOutput $output,
    ): View {
        $filters = $request->validated();

        $drilldown = $report->drilldown($filters, $activity);

        return view('owner-equity-statement.drilldown', [
            'filters' => $filters,
            'activity' => $activity,
            'rowLinks' => $output->drilldownLinks($drilldown['rows'], $request->user()),
        ] + $drilldown);
    }

    public function export(
        OwnerEquityStatementRequest $request,
        OwnerEquityStatementReport $report,
        FinancialReportOutput $output,
    ): StreamedResponse {
        $filters = $request->validated();
        $statement = $report->generate($filters);
        $metadata = $output->metadata($request->user(), "Statement of Changes in Owner's Equity", $filters, 'Accrual basis · Posted general ledger');

        return response()->streamDownload(function () use ($filters, $statement, $metadata, $output): void {
            $stream = fopen('php://output', 'w');
            $output->writeCsvMetadata($stream, $metadata, $filters);
            fputcsv($stream, ['Activity', 'Amount']);
            foreach ($statement['rows'] as $row) {
                fputcsv($stream, [$row['label'], $row['amount']]);
            }
            fputcsv($stream, ['Balance-sheet Closing Equity', $statement['summary']['balance_sheet_closing_equity']]);
            fputcsv($stream, ['Reconciliation Difference', $statement['summary']['reconciliation_difference']]);
            fputcsv($stream, ['Final Ready', $statement['final_ready'] ? 'Yes' : 'No']);
            fclose($stream);
        }, $output->filename('owner-equity-statement', $filters), ['Content-Type' => 'text/csv']);
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
