<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxAttachmentRequest;
use App\Http\Requests\StoreTaxFilingRequest;
use App\Http\Requests\StoreTaxPaymentRequest;
use App\Models\Bir1701qWorksheet;
use App\Models\Bir2551qWorksheet;
use App\Models\TaxFiling;
use App\Models\TaxFilingAttachment;
use App\Services\TaxFilingHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxFilingController extends Controller
{
    public function __construct(private TaxFilingHistory $history) {}

    public function index(): View
    {
        Gate::authorize('viewAny', TaxFiling::class);

        return view('tax-filings.index', ['filings' => TaxFiling::query()->with(['taxObligation.taxPeriod', 'filedBy:id,name', 'payments'])->latest('filing_date')->latest('id')->paginate(25),
            'worksheets2551' => Bir2551qWorksheet::query()->whereNotNull('frozen_at')->whereDoesntHave('taxFiling')->with('taxObligation.taxPeriod')->latest('id')->get(),
            'worksheets1701' => Bir1701qWorksheet::query()->whereNotNull('frozen_at')->whereDoesntHave('taxFiling')->with('taxObligation.taxPeriod')->latest('id')->get(),
            'originalFilings' => TaxFiling::query()->where('is_amended', false)->latest('filing_date')->get(['id', 'bir_form_number', 'return_reference', 'tax_obligation_id'])]);
    }

    public function store(StoreTaxFilingRequest $request): RedirectResponse
    {
        $filing = $this->history->recordFiling($request->validated(), $request->user());

        return to_route('tax-filings.show', $filing)->with('success', 'Manual filing history recorded.');
    }

    public function show(TaxFiling $taxFiling): View
    {
        Gate::authorize('view', $taxFiling);

        return view('tax-filings.show', ['filing' => $taxFiling->load(['taxObligation.taxPeriod', 'filedBy:id,name', 'reviewedBy:id,name', 'originalFiling:id,return_reference', 'payments.recordedBy:id,name', 'attachments.uploadedBy:id,name'])]);
    }

    public function storePayment(StoreTaxPaymentRequest $request, TaxFiling $taxFiling): RedirectResponse
    {
        $this->history->recordPayment($taxFiling, $request->validated(), $request->user());

        return back()->with('success', 'Tax payment history recorded.');
    }

    public function storeAttachment(StoreTaxAttachmentRequest $request, TaxFiling $taxFiling): RedirectResponse
    {
        $this->history->storeAttachment($taxFiling, $request->file('file'), $request->safe()->except('file'), $request->user());

        return back()->with('success', 'Private tax attachment uploaded.');
    }

    public function download(TaxFilingAttachment $taxFilingAttachment): StreamedResponse
    {
        Gate::authorize('tax-attachments.view');
        abort_unless(Storage::disk('local')->exists($taxFilingAttachment->stored_filename), 404);

        return Storage::disk('local')->download($taxFilingAttachment->stored_filename, $taxFilingAttachment->original_filename, ['Content-Type' => $taxFilingAttachment->mime_type]);
    }
}
