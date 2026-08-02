<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveBir1701qWorksheetRequest;
use App\Http\Requests\SaveBir1701qWorksheetRequest;
use App\Models\Bir1701qWorksheet;
use App\Models\TaxObligation;
use App\Services\Bir1701qEncodingPresenter;
use App\Services\Bir1701qPreparation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Bir1701qWorksheetController extends Controller
{
    public function __construct(private Bir1701qPreparation $preparation, private Bir1701qEncodingPresenter $encoding) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Bir1701qWorksheet::class);

        return view('bir-1701q.index', ['obligations' => TaxObligation::query()->where('bir_form_number', '1701Q')->with(['taxPeriod:id,label,tax_year,quarter', 'bir1701qWorksheets'])->latest('id')->paginate(25)]);
    }

    public function store(SaveBir1701qWorksheetRequest $request, TaxObligation $taxObligation): RedirectResponse
    {
        $worksheet = $this->preparation->create($taxObligation, $request->validated(), $request->user());

        return to_route('bir-1701q.show', $worksheet)->with('success', '1701Q preparation worksheet created.');
    }

    public function show(Bir1701qWorksheet $bir1701qWorksheet): View
    {
        Gate::authorize('view', $bir1701qWorksheet);
        $bir1701qWorksheet->load(['taxObligation.taxPeriod', 'previousRevision:id,revision_number']);

        return view('bir-1701q.show', ['worksheet' => $bir1701qWorksheet, 'encodingAmounts' => $this->encoding->amounts($bir1701qWorksheet)]);
    }

    public function update(SaveBir1701qWorksheetRequest $request, Bir1701qWorksheet $bir1701qWorksheet): RedirectResponse
    {
        $this->preparation->update($bir1701qWorksheet, $request->validated());

        return back()->with('success', 'Worksheet recalculated.');
    }

    public function submit(Bir1701qWorksheet $bir1701qWorksheet): RedirectResponse
    {
        Gate::authorize('update', $bir1701qWorksheet);
        $this->preparation->submit($bir1701qWorksheet);

        return back()->with('success', 'Worksheet submitted for review.');
    }

    public function review(Bir1701qWorksheet $bir1701qWorksheet): RedirectResponse
    {
        Gate::authorize('review', $bir1701qWorksheet);
        $this->preparation->review($bir1701qWorksheet, request()->user());

        return back()->with('success', 'Worksheet review recorded.');
    }

    public function approve(ApproveBir1701qWorksheetRequest $request, Bir1701qWorksheet $bir1701qWorksheet): RedirectResponse
    {
        $this->preparation->approve($bir1701qWorksheet, $request->user());

        return back()->with('success', 'Worksheet approved and frozen as ready to file.');
    }

    public function revise(SaveBir1701qWorksheetRequest $request, Bir1701qWorksheet $bir1701qWorksheet): RedirectResponse
    {
        $revision = $this->preparation->create($bir1701qWorksheet->taxObligation, $request->validated(), $request->user(), $bir1701qWorksheet);

        return to_route('bir-1701q.show', $revision)->with('success', 'A new worksheet revision was created.');
    }

    public function export(Bir1701qWorksheet $bir1701qWorksheet): StreamedResponse
    {
        Gate::authorize('export', $bir1701qWorksheet);

        return response()->streamDownload(function () use ($bir1701qWorksheet): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['BIR Form 1701Q Preparation Worksheet', 'Review worksheet only - not an official electronic submission file']);
            fputcsv($stream, ['Field', 'Exact stored amount', 'Whole-peso amount for encoding']);
            $encodingAmounts = $this->encoding->amounts($bir1701qWorksheet);
            foreach (['taxable_year', 'quarter', 'return_type', 'revision_number', 'income_tax_method', 'deduction_method', 'cumulative_gross_sales', 'sales_returns_discounts', 'net_sales', 'cost_of_sales', 'other_income', 'gross_income', 'financial_itemized_deductions', 'osd_deduction', 'manual_deduction_adjustment', 'taxable_income_adjustment', 'taxable_income', 'income_tax_due', 'prior_quarter_payments', 'verified_creditable_withholding', 'manual_creditable_withholding', 'other_allowable_credits', 'surcharge', 'interest', 'compromise_penalty', 'total_amount_payable', 'filing_status', 'review_status'] as $field) {
                fputcsv($stream, [str($field)->headline()->toString(), $bir1701qWorksheet->getAttribute($field), $encodingAmounts[$field]['whole_peso'] ?? '']);
            }
            fclose($stream);
        }, 'bir-1701q-'.$bir1701qWorksheet->taxable_year.'-q'.$bir1701qWorksheet->quarter.'-r'.$bir1701qWorksheet->revision_number.'.csv', ['Content-Type' => 'text/csv']);
    }
}
