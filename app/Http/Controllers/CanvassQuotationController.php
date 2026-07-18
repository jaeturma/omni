<?php

namespace App\Http\Controllers;

use App\Actions\SaveCanvassQuotation;
use App\Http\Requests\StoreCanvassQuotationRequest;
use App\Http\Requests\UpdateCanvassQuotationRequest;
use App\Models\CanvassQuotation;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CanvassQuotationController extends Controller
{
    public function store(StoreCanvassQuotationRequest $request, PurchaseRequest $purchaseRequest, SaveCanvassQuotation $save): RedirectResponse
    {
        $save->handle($purchaseRequest, $request->validated(), $request->user()->id);

        return back()->with('success', 'Supplier quotation recorded.');
    }

    public function update(UpdateCanvassQuotationRequest $request, PurchaseRequest $purchaseRequest, CanvassQuotation $canvassQuotation, SaveCanvassQuotation $save): RedirectResponse
    {
        abort_unless($canvassQuotation->purchase_request_id === $purchaseRequest->id, 404);
        $save->handle($purchaseRequest, $request->validated(), $request->user()->id, $canvassQuotation);

        return back()->with('success', 'Supplier quotation updated.');
    }

    public function destroy(PurchaseRequest $purchaseRequest, CanvassQuotation $canvassQuotation): RedirectResponse
    {
        abort_unless($canvassQuotation->purchase_request_id === $purchaseRequest->id, 404);
        Gate::authorize('delete', $canvassQuotation);
        $canvassQuotation->delete();

        return back()->with('success', 'Supplier quotation removed.');
    }
}
