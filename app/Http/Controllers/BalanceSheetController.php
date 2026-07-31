<?php

namespace App\Http\Controllers;

use App\Http\Requests\BalanceSheetRequest;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Reports\BalanceSheetReport;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheetController extends Controller
{
    public function index(BalanceSheetRequest $request, BalanceSheetReport $report): View
    {
        $filters = $request->validated();

        return view('balance-sheet.index', $this->viewData($filters) + $report->generate($filters));
    }

    public function print(BalanceSheetRequest $request, BalanceSheetReport $report): View
    {
        $filters = $request->validated();

        return view('balance-sheet.print', ['filters' => $filters] + $report->generate($filters));
    }

    public function drilldown(BalanceSheetRequest $request, Account $account, BalanceSheetReport $report): View
    {
        $filters = $request->validated();

        return view('balance-sheet.drilldown', [
            'filters' => $filters,
            'account' => $account,
        ] + $report->drilldown($filters, $account));
    }

    public function export(BalanceSheetRequest $request, BalanceSheetReport $report): StreamedResponse
    {
        $filters = $request->validated();
        $statement = $report->generate($filters);

        return response()->streamDownload(function () use ($filters, $statement): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Balance Sheet']);
            foreach ($filters as $parameter => $value) {
                fputcsv($stream, [str($parameter)->headline()->toString(), is_bool($value) ? ($value ? 'Yes' : 'No') : $value]);
            }
            fputcsv($stream, []);
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
        }, 'balance-sheet-'.$filters['as_of'].'.csv', ['Content-Type' => 'text/csv']);
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
