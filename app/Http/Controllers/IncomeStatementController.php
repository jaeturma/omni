<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeStatementRequest;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Reports\IncomeStatementReport;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeStatementController extends Controller
{
    public function index(IncomeStatementRequest $request, IncomeStatementReport $report): View
    {
        $filters = $request->validated();

        return view('income-statement.index', $this->viewData($filters) + $report->generate($filters));
    }

    public function print(IncomeStatementRequest $request, IncomeStatementReport $report): View
    {
        $filters = $request->validated();

        return view('income-statement.print', ['filters' => $filters] + $report->generate($filters));
    }

    public function drilldown(IncomeStatementRequest $request, Account $account, IncomeStatementReport $report): View
    {
        $filters = $request->validated();

        return view('income-statement.drilldown', [
            'filters' => $filters,
            'account' => $account,
        ] + $report->drilldown($filters, $account));
    }

    public function export(IncomeStatementRequest $request, IncomeStatementReport $report): StreamedResponse
    {
        $filters = $request->validated();
        $statement = $report->generate($filters);

        return response()->streamDownload(function () use ($filters, $statement): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Income Statement']);
            foreach ($filters as $parameter => $value) {
                fputcsv($stream, [str($parameter)->headline()->toString(), is_bool($value) ? ($value ? 'Yes' : 'No') : $value]);
            }
            fputcsv($stream, []);
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
        }, 'income-statement-'.$filters['start_date'].'-'.$filters['end_date'].'.csv', ['Content-Type' => 'text/csv']);
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
