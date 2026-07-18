<?php

namespace App\Http\Controllers;

use App\Actions\ConvertPurchaseRequestToPurchaseOrder;
use App\Actions\SavePurchaseOrder;
use App\Actions\TransitionPurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\ConvertPurchaseRequestToPurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\TransitionPurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PurchaseOrder::class);
        $purchaseOrders = PurchaseOrder::query()->with('supplier:id,name')->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('purchase_order_number', 'like', '%'.$request->string('search').'%')->orWhere('supplier_name', 'like', '%'.$request->string('search').'%')->orWhere('supplier_quotation_reference', 'like', '%'.$request->string('search').'%')))->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))->latest('order_date')->latest('id')->paginate(25)->withQueryString();

        return view('purchase-orders.index', compact('purchaseOrders'));
    }

    public function create(): View
    {
        Gate::authorize('create', PurchaseOrder::class);

        return view('purchase-orders.create', $this->formOptions());
    }

    public function store(StorePurchaseOrderRequest $request, SavePurchaseOrder $save): RedirectResponse
    {
        $order = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Purchase order draft created.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('view', $purchaseOrder);

        return view('purchase-orders.show', ['purchaseOrder' => $purchaseOrder->load(['supplier', 'purchaseRequest', 'canvassQuotation', 'lines'])]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('update', $purchaseOrder);

        return view('purchase-orders.edit', ['purchaseOrder' => $purchaseOrder->load('lines')] + $this->formOptions());
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, SavePurchaseOrder $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $purchaseOrder);

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('delete', $purchaseOrder);
        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order draft deleted.');
    }

    public function transition(TransitionPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, TransitionPurchaseOrder $transition): RedirectResponse
    {
        $transition->handle($purchaseOrder, PurchaseOrderStatus::from($request->validated('status')), $request->user()->id, $request->validated('reason'));

        return back()->with('success', 'Purchase order status updated.');
    }

    public function convert(ConvertPurchaseRequestToPurchaseOrderRequest $request, PurchaseRequest $purchaseRequest, ConvertPurchaseRequestToPurchaseOrder $convert): RedirectResponse
    {
        $order = $convert->handle($purchaseRequest, $request->validated(), $request->user()->id);

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Purchase request converted to a purchase order draft.');
    }

    public function print(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('print', $purchaseOrder);

        return view('purchase-orders.print', ['purchaseOrder' => $purchaseOrder->load(['supplier', 'purchaseRequest', 'lines'])]);
    }

    private function formOptions(): array
    {
        return ['suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(), 'items' => ProductService::query()->with('unitOfMeasure:id,code,name')->where('status', 'active')->orderBy('name')->get()];
    }
}
