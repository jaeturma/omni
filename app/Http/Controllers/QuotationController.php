<?php

namespace App\Http\Controllers;

use App\Actions\SaveQuotation;
use App\Actions\TransitionQuotation;
use App\Enums\QuotationStatus;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\TransitionQuotationRequest;
use App\Http\Requests\UpdateQuotationRequest;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Quotation::class);
        $quotations = Quotation::query()->with('customer:id,name')->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('quotation_number', 'like', '%'.$request->string('search').'%')->orWhere('customer_name', 'like', '%'.$request->string('search').'%')->orWhere('reference', 'like', '%'.$request->string('search').'%')))->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))->latest('quotation_date')->latest('id')->paginate(25)->withQueryString();

        return view('quotations.index', compact('quotations'));
    }

    public function create(): View
    {
        Gate::authorize('create', Quotation::class);

        return view('quotations.create', $this->formOptions());
    }

    public function store(StoreQuotationRequest $request, SaveQuotation $save): RedirectResponse
    {
        $quotation = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation draft created.');
    }

    public function show(Quotation $quotation): View
    {
        Gate::authorize('view', $quotation);

        return view('quotations.show', ['quotation' => $quotation->load(['customer', 'lines'])]);
    }

    public function edit(Quotation $quotation): View
    {
        Gate::authorize('update', $quotation);

        return view('quotations.edit', ['quotation' => $quotation->load('lines')] + $this->formOptions());
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation, SaveQuotation $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $quotation);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation draft updated.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('delete', $quotation);
        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation draft deleted.');
    }

    public function transition(TransitionQuotationRequest $request, Quotation $quotation, TransitionQuotation $transition): RedirectResponse
    {
        $transition->handle($quotation, QuotationStatus::from($request->validated('status')), $request->user()->id, $request->validated('reason'));

        return back()->with('success', 'Quotation status updated.');
    }

    public function print(Quotation $quotation): View
    {
        Gate::authorize('print', $quotation);

        return view('quotations.print', ['quotation' => $quotation->load(['customer', 'lines'])]);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'address', 'contact_person', 'email', 'phone']),
            'items' => ProductService::query()->with('unitOfMeasure:id,code,name')->where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name', 'description', 'type', 'unit_of_measure_id', 'selling_price']),
        ];
    }
}
