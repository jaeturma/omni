<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyWithholdingCertificateRequest;
use App\Models\GovernmentDeduction;
use App\Models\TaxObligation;
use App\Services\WithholdingCertificateReconciliation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WithholdingCertificateReconciliationController extends Controller
{
    public function __construct(private WithholdingCertificateReconciliation $reconciliation) {}

    public function index(Request $request): View
    {
        Gate::authorize('withholding-certificates.view');
        $year = $request->integer('year', now()->year);

        return view('withholding-reconciliation.index', $this->reconciliation->summary($year) + ['year' => $year]);
    }

    public function apply(ApplyWithholdingCertificateRequest $request, GovernmentDeduction $governmentDeduction): RedirectResponse
    {
        $obligation = TaxObligation::query()->findOrFail($request->integer('tax_obligation_id'));
        $this->reconciliation->apply($governmentDeduction, $obligation, $request->validated(), $request->user());

        return back()->with('success', 'Certificate credit applied to the selected return.');
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('withholding-reconciliation.export');
        $year = $request->integer('year', now()->year);
        $summary = $this->reconciliation->summary($year);

        return response()->streamDownload(function () use ($summary): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Certificate', 'Customer', 'Invoice', 'Amount', 'Applied', 'Remaining', 'Ledger amount', 'Status']);
            foreach ($summary['certificates'] as $certificate) {
                fputcsv($stream, [$certificate->certificate_number, $certificate->customer->name, $certificate->salesInvoice->invoice_number, $certificate->amount, $certificate->appliedAmount(), $certificate->remainingAmount(), $certificate->journalEntryLine ? bcsub($certificate->journalEntryLine->debit, $certificate->journalEntryLine->credit, 4) : '', $certificate->status->value]);
            }
            fclose($stream);
        }, 'withholding-reconciliation-'.$year.'.csv', ['Content-Type' => 'text/csv']);
    }
}
