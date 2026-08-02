<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveBir2551qWorksheetRequest;
use App\Http\Requests\SaveBir2551qWorksheetRequest;
use App\Models\Bir2551qWorksheet;
use App\Models\TaxObligation;
use App\Services\Bir2551qPreparation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Bir2551qWorksheetController extends Controller
{
    public function __construct(private Bir2551qPreparation $preparation) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Bir2551qWorksheet::class);

        return view('bir-2551q.index', ['obligations' => TaxObligation::query()->where('bir_form_number', '2551Q')->with(['taxPeriod:id,label,tax_year,quarter', 'reconciliation:id,tax_obligation_id,critical_difference_count', 'bir2551qWorksheets'])->latest('id')->paginate(25)]);
    }

    public function store(SaveBir2551qWorksheetRequest $request, TaxObligation $taxObligation): RedirectResponse
    {
        $worksheet = $this->preparation->create($taxObligation, $request->validated(), $request->user());

        return to_route('bir-2551q.show', $worksheet)->with('success', '2551Q preparation worksheet created.');
    }

    public function show(Bir2551qWorksheet $bir2551qWorksheet): View
    {
        Gate::authorize('view', $bir2551qWorksheet);
        $bir2551qWorksheet->load(['taxObligation.taxPeriod', 'previousRevision:id,revision_number', 'taxReconciliation:id']);

        return view('bir-2551q.show', ['worksheet' => $bir2551qWorksheet]);
    }

    public function update(SaveBir2551qWorksheetRequest $request, Bir2551qWorksheet $bir2551qWorksheet): RedirectResponse
    {
        $this->preparation->update($bir2551qWorksheet, $request->validated());

        return back()->with('success', 'Worksheet recalculated.');
    }

    public function submit(Bir2551qWorksheet $bir2551qWorksheet): RedirectResponse
    {
        Gate::authorize('update', $bir2551qWorksheet);
        $this->preparation->submit($bir2551qWorksheet);

        return back()->with('success', 'Worksheet submitted for review.');
    }

    public function review(Bir2551qWorksheet $bir2551qWorksheet): RedirectResponse
    {
        Gate::authorize('review', $bir2551qWorksheet);
        $this->preparation->review($bir2551qWorksheet, request()->user());

        return back()->with('success', 'Worksheet review recorded.');
    }

    public function approve(ApproveBir2551qWorksheetRequest $request, Bir2551qWorksheet $bir2551qWorksheet): RedirectResponse
    {
        $this->preparation->approve($bir2551qWorksheet, $request->user());

        return back()->with('success', 'Worksheet approved and frozen as ready to file.');
    }

    public function revise(SaveBir2551qWorksheetRequest $request, Bir2551qWorksheet $bir2551qWorksheet): RedirectResponse
    {
        $revision = $this->preparation->create($bir2551qWorksheet->taxObligation, $request->validated(), $request->user(), $bir2551qWorksheet);

        return to_route('bir-2551q.show', $revision)->with('success', 'A new worksheet revision was created.');
    }

    public function export(Bir2551qWorksheet $bir2551qWorksheet): StreamedResponse
    {
        Gate::authorize('export', $bir2551qWorksheet);

        return response()->streamDownload(function () use ($bir2551qWorksheet): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['BIR Form 2551Q Preparation Worksheet', 'Review worksheet only — not an official electronic submission file']);
            foreach (['return_year', 'quarter', 'return_type', 'revision_number', 'basis_type', 'gross_taxable_amount', 'excluded_amount', 'taxable_amount', 'tax_rate', 'gross_tax_due', 'allowable_credits', 'government_tax_withheld', 'prior_payment', 'surcharge', 'interest', 'compromise_penalty', 'total_amount_payable', 'filing_status', 'review_status'] as $field) {
                fputcsv($stream, [str($field)->headline()->toString(), $bir2551qWorksheet->getAttribute($field)]);
            }
            fclose($stream);
        }, 'bir-2551q-'.$bir2551qWorksheet->return_year.'-q'.$bir2551qWorksheet->quarter.'-r'.$bir2551qWorksheet->revision_number.'.csv', ['Content-Type' => 'text/csv']);
    }
}
