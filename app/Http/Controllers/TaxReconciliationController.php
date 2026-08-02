<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewTaxReconciliationAdjustmentRequest;
use App\Http\Requests\StoreTaxReconciliationAdjustmentRequest;
use App\Models\TaxObligation;
use App\Models\TaxReconciliation;
use App\Models\TaxReconciliationAdjustment;
use App\Models\User;
use App\Services\SalesTaxReconciliation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxReconciliationController extends Controller
{
    public function __construct(private SalesTaxReconciliation $service) {}

    public function index(): View
    {
        Gate::authorize('viewAny', TaxReconciliation::class);

        return view('tax-reconciliations.index', ['obligations' => TaxObligation::query()->with(['taxPeriod:id,label,capture_start,period_end', 'reconciliation:id,tax_obligation_id,difference,critical_difference_count,generated_at'])->latest('id')->paginate(25)]);
    }

    public function generate(TaxObligation $taxObligation): RedirectResponse
    {
        Gate::authorize('tax-reconciliation.adjust');
        $reconciliation = $this->service->generate($taxObligation, request()->user());

        return to_route('tax-reconciliations.show', $reconciliation)->with('success', 'Tax reconciliation generated.');
    }

    public function show(TaxReconciliation $taxReconciliation): View
    {
        Gate::authorize('view', $taxReconciliation);
        $taxReconciliation->load(['taxObligation.taxPeriod', 'generatedBy:id,name', 'adjustments.reviewer:id,name', 'adjustments.reviewedBy:id,name']);

        return view('tax-reconciliations.show', ['reconciliation' => $taxReconciliation, 'reviewers' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function storeAdjustment(StoreTaxReconciliationAdjustmentRequest $request, TaxReconciliation $taxReconciliation): RedirectResponse
    {
        $taxReconciliation->adjustments()->create($request->validated() + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Adjustment submitted for review.');
    }

    public function reviewAdjustment(ReviewTaxReconciliationAdjustmentRequest $request, TaxReconciliationAdjustment $taxReconciliationAdjustment): RedirectResponse
    {
        $reconciliation = $this->service->reviewAdjustment($taxReconciliationAdjustment, $request->validated('status'), $request->validated('review_notes'), $request->user());

        return to_route('tax-reconciliations.show', $reconciliation)->with('success', 'Adjustment review recorded.');
    }

    public function export(TaxReconciliation $taxReconciliation): StreamedResponse
    {
        Gate::authorize('export', $taxReconciliation);
        $taxReconciliation->load('taxObligation.taxPeriod');

        return response()->streamDownload(function () use ($taxReconciliation): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Metric', 'Amount']);
            foreach (['gross_sales', 'credit_adjustments', 'operational_net_sales', 'receipt_basis', 'ledger_revenue', 'customer_withholding', 'approved_adjustments', 'difference'] as $metric) {
                fputcsv($stream, [str($metric)->headline()->toString(), $taxReconciliation->getAttribute($metric)]);
            }
            fclose($stream);
        }, 'sales-tax-reconciliation-'.$taxReconciliation->taxObligation->taxPeriod->label.'.csv', ['Content-Type' => 'text/csv']);
    }
}
