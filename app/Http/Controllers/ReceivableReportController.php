<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceivableReportRequest;
use App\Models\Customer;
use App\Reports\ReceivablesReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivableReportController extends Controller
{
    public function index(ReceivableReportRequest $request, ReceivablesReport $report): View
    {
        $filters = $request->validated();

        return view('receivables.index', $this->viewData($filters) + ['rows' => $report->detailPaginator($filters)]);
    }

    public function summary(ReceivableReportRequest $request, ReceivablesReport $report): View
    {
        $filters = $request->validated();

        return view('receivables.summary', $this->viewData($filters) + ['rows' => $report->summary($report->detailCollection($filters))]);
    }

    public function unapplied(ReceivableReportRequest $request, ReceivablesReport $report): View
    {
        $filters = $request->validated();

        return view('receivables.unapplied', $this->viewData($filters) + ['rows' => $report->unappliedPaginator($filters)]);
    }

    public function print(ReceivableReportRequest $request, ReceivablesReport $report): View
    {
        $filters = $request->validated();

        return view('receivables.print', ['filters' => $filters, 'rows' => $report->detailCollection($filters)]);
    }

    public function export(ReceivableReportRequest $request, ReceivablesReport $report): StreamedResponse
    {
        Gate::authorize('receivables.export');
        $filters = $request->validated();
        $rows = $report->detailCollection($filters);

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Invoice', 'Customer', 'Type', 'Invoice Date', 'Due Date', 'Receivable', 'Allocated', 'Balance', 'Days Overdue', 'Bucket']);
            foreach ($rows as $row) {
                $invoice = $row['invoice'];
                fputcsv($stream, [$invoice->invoice_number, $invoice->customer->name, $invoice->customer->type,
                    $invoice->invoice_date->toDateString(), $invoice->due_date->toDateString(), $invoice->total_receivable,
                    $row['allocated'], $row['balance'], $row['daysOverdue'], $row['bucket']]);
            }
            fclose($stream);
        }, 'receivables-'.$filters['as_of'].'.csv', ['Content-Type' => 'text/csv']);
    }

    public function statement(ReceivableReportRequest $request, Customer $customer, ReceivablesReport $report): View
    {
        Gate::authorize('customer-statements.view');
        $asOf = Carbon::parse($request->validated('as_of'));

        return view('receivables.statement', ['customer' => $customer, 'asOf' => $asOf, ...$report->statement($customer, $asOf)]);
    }

    public function statementPrint(ReceivableReportRequest $request, Customer $customer, ReceivablesReport $report): View
    {
        Gate::authorize('customer-statements.view');
        $asOf = Carbon::parse($request->validated('as_of'));

        return view('receivables.statement-print', ['customer' => $customer, 'asOf' => $asOf, ...$report->statement($customer, $asOf)]);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function viewData(array $filters): array
    {
        return ['filters' => $filters, 'customers' => Customer::orderBy('name')->get(['id', 'name'])];
    }
}
