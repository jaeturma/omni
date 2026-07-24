<?php

namespace App\Http\Controllers;

use App\Enums\CashTransactionType;
use App\Enums\FinancialAccountType;
use App\Http\Requests\CashPositionReportRequest;
use App\Models\FinancialAccount;
use App\Reports\CashPositionReport;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashPositionReportController extends Controller
{
    public function index(CashPositionReportRequest $request, CashPositionReport $report): View
    {
        $filters = $request->validated();

        return view('cash-reports.index', $this->viewData($filters, $report) + ['activity' => $report->activityPaginator($filters)]);
    }

    public function print(CashPositionReportRequest $request, CashPositionReport $report): View
    {
        $filters = $request->validated();

        return view('cash-reports.print', $this->viewData($filters, $report) + [
            'activity' => $report->postedQuery($filters)->with('financialAccount:id,code,name')->latest('transaction_date')->get(),
        ]);
    }

    public function export(CashPositionReportRequest $request, CashPositionReport $report): StreamedResponse
    {
        Gate::authorize('cash-reports.export');
        $filters = $request->validated();
        $accounts = FinancialAccount::query()->pluck('code', 'id');

        return response()->streamDownload(function () use ($report, $filters, $accounts): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Date', 'Account', 'Type', 'Reference', 'Amount', 'Fee', 'Signed Movement', 'Reconciliation']);
            foreach ($report->postedQuery($filters)->latest('transaction_date')->cursor() as $transaction) {
                fputcsv($stream, [$transaction->transaction_date->toDateString(), $accounts[$transaction->financial_account_id],
                    $transaction->type->value, $transaction->reference_number, $transaction->amount, $transaction->fee_amount,
                    $report->signed($transaction),
                    $transaction->finalized_reconciliation_match_exists ? 'reconciled' : 'unreconciled']);
            }
            fclose($stream);
        }, 'cash-position-'.$filters['start_date'].'-'.$filters['end_date'].'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function viewData(array $filters, CashPositionReport $report): array
    {
        return [
            'filters' => $filters, 'report' => $report->summary($filters, Gate::allows('reconciliation-reports.view')),
            'accounts' => FinancialAccount::query()->orderBy('name')->get(['id', 'code', 'name']),
            'accountTypes' => FinancialAccountType::cases(), 'transactionTypes' => CashTransactionType::cases(),
            'canViewReconciliation' => Gate::allows('reconciliation-reports.view'),
        ];
    }
}
