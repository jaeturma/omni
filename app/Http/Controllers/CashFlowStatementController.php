<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashFlowStatementRequest;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Reports\CashFlowStatementReport;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowStatementController extends Controller
{
    public function index(CashFlowStatementRequest $request, CashFlowStatementReport $report): View
    {
        $filters = $request->validated();

        return view('cash-flow-statement.index', $this->viewData($filters) + $report->generate($filters));
    }

    public function print(CashFlowStatementRequest $request, CashFlowStatementReport $report): View
    {
        $filters = $request->validated();

        return view('cash-flow-statement.print', ['filters' => $filters] + $report->generate($filters));
    }

    public function drilldown(
        CashFlowStatementRequest $request,
        Account $account,
        CashFlowStatementReport $report,
    ): View {
        $filters = $request->validated();

        return view('cash-flow-statement.drilldown', [
            'filters' => $filters,
            'account' => $account,
        ] + $report->drilldown($filters, $account));
    }

    public function mappings(CashFlowStatementRequest $request, CashFlowStatementReport $report): View
    {
        return view('cash-flow-statement.mappings', [
            'filters' => $request->validated(),
            'accounts' => $report->mappingReview(),
        ]);
    }

    public function export(
        CashFlowStatementRequest $request,
        CashFlowStatementReport $report,
    ): StreamedResponse {
        $filters = $request->validated();
        $statement = $report->generate($filters);

        return response()->streamDownload(function () use ($filters, $statement): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Cash Flow Statement — Indirect Method']);
            foreach ($filters as $parameter => $value) {
                fputcsv($stream, [str($parameter)->headline()->toString(), $value]);
            }
            fputcsv($stream, []);
            fputcsv($stream, ['Section', 'Account Code', 'Activity', 'Amount']);
            foreach ($statement['sections'] as $section) {
                foreach ($section['rows'] as $row) {
                    fputcsv($stream, [$section['label'], $row['account']?->code, $row['label'], $row['amount']]);
                }
                fputcsv($stream, [$section['label'].' Total', null, null, $section['total']]);
            }
            fputcsv($stream, []);
            foreach (['beginning_cash', 'net_change', 'ending_cash', 'balance_sheet_cash', 'reconciliation_difference'] as $key) {
                fputcsv($stream, [str($key)->headline()->toString(), null, null, $statement['summary'][$key]]);
            }
            fputcsv($stream, ['Final Ready', null, null, $statement['final_ready'] ? 'Yes' : 'No']);
            fclose($stream);
        }, 'cash-flow-statement-'.$filters['start_date'].'-'.$filters['end_date'].'.csv', ['Content-Type' => 'text/csv']);
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
