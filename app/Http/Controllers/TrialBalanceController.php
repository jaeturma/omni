<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrialBalanceRequest;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Reports\SubledgerReconciliationReport;
use App\Reports\TrialBalanceReport;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalanceController extends Controller
{
    public function index(TrialBalanceRequest $request, TrialBalanceReport $report): View
    {
        $filters = $request->validated();

        return view('trial-balance.index', $this->viewData($filters) + $report->generate($filters));
    }

    public function reconciliations(TrialBalanceRequest $request, SubledgerReconciliationReport $report): View
    {
        $filters = $request->validated();

        return view('trial-balance.reconciliations', $this->viewData($filters) + ['rows' => $report->generate($filters)]);
    }

    public function print(TrialBalanceRequest $request, TrialBalanceReport $trialBalance, SubledgerReconciliationReport $reconciliations): View
    {
        $filters = $request->validated();
        $isReconciliation = $request->routeIs('subledger-reconciliations.*');
        $data = $isReconciliation
            ? ['rows' => $reconciliations->generate($filters)]
            : $trialBalance->generate($filters, false);

        return view('trial-balance.print', compact('filters', 'isReconciliation') + $data);
    }

    public function export(TrialBalanceRequest $request, TrialBalanceReport $trialBalance, SubledgerReconciliationReport $reconciliations): StreamedResponse
    {
        $filters = $request->validated();
        $isReconciliation = $request->routeIs('subledger-reconciliations.*');
        $rows = $isReconciliation
            ? $reconciliations->generate($filters)
            : $trialBalance->generate($filters, false)['rows'];

        return response()->streamDownload(function () use ($rows, $isReconciliation): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $isReconciliation
                ? ['Account', 'Control Type', 'Ledger', 'Subledger', 'Difference', 'Sources', 'Status']
                : ['Account', 'Name', 'Opening Debit', 'Opening Credit', 'Movement Debit', 'Movement Credit', 'Closing Debit', 'Closing Credit']);
            foreach ($rows as $row) {
                fputcsv($stream, $isReconciliation ? [
                    $row['account']->code, $row['account']->control_account_type, $row['ledger'],
                    $row['subledger'], $row['difference'], $row['source_count'],
                    ! $row['available'] ? 'Unavailable' : (bccomp($row['difference'], '0', 4) === 0 ? 'Reconciled' : 'Difference'),
                ] : [
                    $row['account']->code, $row['account']->name, $row['opening_debit'], $row['opening_credit'],
                    $row['movement_debit'], $row['movement_credit'], $row['closing_debit'], $row['closing_credit'],
                ]);
            }
            fclose($stream);
        }, ($isReconciliation ? 'subledger-reconciliation-' : 'trial-balance-').$filters['as_of'].'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function viewData(array $filters): array
    {
        return [
            'filters' => $filters,
            'periods' => FiscalPeriod::query()->with('fiscalYear:id,name')->latest('starts_on')->get(['id', 'fiscal_year_id', 'name', 'starts_on', 'ends_on']),
            'accounts' => Account::query()->ordered()->get(['id', 'code', 'name']),
        ];
    }
}
