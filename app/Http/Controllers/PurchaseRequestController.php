<?php

namespace App\Http\Controllers;

use App\Actions\SavePurchaseRequest;
use App\Actions\TransitionPurchaseRequest;
use App\Enums\PurchaseRequestStatus;
use App\Http\Requests\StorePurchaseRequestRequest;
use App\Http\Requests\TransitionPurchaseRequestRequest;
use App\Http\Requests\UpdatePurchaseRequestRequest;
use App\Models\ProductService;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PurchaseRequest::class);
        $purchaseRequests = PurchaseRequest::query()->with('requester:id,name')
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('request_number', 'like', '%'.$request->string('search').'%')->orWhere('purpose', 'like', '%'.$request->string('search').'%')->orWhere('project_reference', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('request_date')->latest('id')->paginate(25)->withQueryString();

        return view('purchase-requests.index', compact('purchaseRequests'));
    }

    public function create(): View
    {
        Gate::authorize('create', PurchaseRequest::class);

        return view('purchase-requests.create', $this->formOptions());
    }

    public function store(StorePurchaseRequestRequest $request, SavePurchaseRequest $save): RedirectResponse
    {
        $record = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('purchase-requests.show', $record)->with('success', 'Purchase request draft created.');
    }

    public function show(PurchaseRequest $purchaseRequest): View
    {
        Gate::authorize('view', $purchaseRequest);

        return view('purchase-requests.show', ['purchaseRequest' => $purchaseRequest->load(['requester', 'lines.preferredSupplier', 'canvassQuotations.supplier', 'purchaseOrder']), 'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get()]);
    }

    public function edit(PurchaseRequest $purchaseRequest): View
    {
        Gate::authorize('update', $purchaseRequest);

        return view('purchase-requests.edit', ['purchaseRequest' => $purchaseRequest->load('lines')] + $this->formOptions());
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest, SavePurchaseRequest $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $purchaseRequest);

        return redirect()->route('purchase-requests.show', $purchaseRequest)->with('success', 'Purchase request updated.');
    }

    public function destroy(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('delete', $purchaseRequest);
        $purchaseRequest->delete();

        return redirect()->route('purchase-requests.index')->with('success', 'Purchase request draft deleted.');
    }

    public function transition(TransitionPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest, TransitionPurchaseRequest $transition): RedirectResponse
    {
        $transition->handle($purchaseRequest, PurchaseRequestStatus::from($request->validated('status')), $request->user()->id, $request->validated('reason'));

        return back()->with('success', 'Purchase request status updated.');
    }

    private function formOptions(): array
    {
        return ['requesters' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name']), 'items' => ProductService::query()->with('unitOfMeasure:id,code,name')->where('status', 'active')->orderBy('name')->get(), 'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get()];
    }
}
