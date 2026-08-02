<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayableReportRequest;
use App\Models\Supplier;
use App\Reports\AccountsPayableReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountsPayableReportController extends Controller
{
    public function index(PayableReportRequest $request, AccountsPayableReport $report): View
    {
        $filters = $request->validated();

        return view('payables.index', $this->viewData($filters) + ['rows' => $report->detailPaginator($filters)]);
    }

    public function summary(PayableReportRequest $request, AccountsPayableReport $report): View
    {
        $filters = $request->validated();

        return view('payables.summary', $this->viewData($filters) + ['rows' => $report->summary($report->detailCollection($filters))]);
    }

    public function unapplied(PayableReportRequest $request, AccountsPayableReport $report): View
    {
        $filters = $request->validated();

        return view('payables.unapplied', $this->viewData($filters) + ['rows' => $report->unappliedPaginator($filters)]);
    }

    public function expenses(PayableReportRequest $request, AccountsPayableReport $report): View
    {
        $filters = $request->validated();

        return view('payables.expenses', $this->viewData($filters) + ['rows' => $report->expensePaginator($filters)]);
    }

    public function print(PayableReportRequest $request, AccountsPayableReport $report): View
    {
        $filters = $request->validated();

        return view('payables.print', ['filters' => $filters, 'rows' => $report->detailCollection($filters)]);
    }

    public function export(PayableReportRequest $request, AccountsPayableReport $report): StreamedResponse
    {
        Gate::authorize('payables.export');
        $filters = $request->validated();

        return response()->streamDownload(function () use ($report, $filters): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Invoice', 'Supplier', 'Invoice Date', 'Due Date', 'Payable', 'Allocated', 'Balance', 'Days Overdue', 'Bucket']);
            foreach ($report->detailLazy($filters) as $row) {
                $invoice = $row['invoice'];
                fputcsv($stream, [$invoice->supplier_invoice_number, $invoice->supplier->name, $invoice->invoice_date->toDateString(),
                    $invoice->due_date->toDateString(), $invoice->total_payable, $row['allocated'], $row['balance'], $row['daysOverdue'], $row['bucket']]);
            }
            fclose($stream);
        }, 'accounts-payable-'.$filters['as_of'].'.csv', ['Content-Type' => 'text/csv']);
    }

    public function statement(PayableReportRequest $request, Supplier $supplier, AccountsPayableReport $report): View
    {
        $asOf = Carbon::parse($request->validated('as_of'));

        return view('payables.statement', ['supplier' => $supplier, 'asOf' => $asOf, ...$report->statement($supplier, $asOf)]);
    }

    public function statementPrint(PayableReportRequest $request, Supplier $supplier, AccountsPayableReport $report): View
    {
        $asOf = Carbon::parse($request->validated('as_of'));

        return view('payables.statement-print', ['supplier' => $supplier, 'asOf' => $asOf, ...$report->statement($supplier, $asOf)]);
    }

    private function viewData(array $filters): array
    {
        return ['filters' => $filters, 'suppliers' => Supplier::orderBy('name')->get(['id', 'name'])];
    }
}
