<?php

namespace App\Http\Controllers;

use App\Actions\ConvertQuotationToSalesOrder;
use App\Actions\SaveSalesOrder;
use App\Actions\TransitionSalesOrder;
use App\Actions\UpdateSalesOrderFulfillment;
use App\Enums\SalesOrderStatus;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\TransitionSalesOrderRequest;
use App\Http\Requests\UpdateSalesOrderFulfillmentRequest;
use App\Http\Requests\UpdateSalesOrderRequest;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function index(Request $r): View
    {
        Gate::authorize('viewAny', SalesOrder::class);
        $salesOrders = SalesOrder::with('customer:id,name')->when($r->string('search')->isNotEmpty(), fn ($q) => $q->where(fn ($q) => $q->where('sales_order_number', 'like', '%'.$r->string('search').'%')->orWhere('customer_name', 'like', '%'.$r->string('search').'%')->orWhere('customer_po_number', 'like', '%'.$r->string('search').'%')))->when($r->filled('status'), fn ($q) => $q->where('status', $r->string('status')))->latest('order_date')->paginate(25)->withQueryString();

        return view('sales-orders.index', compact('salesOrders'));
    }

    public function create(): View
    {
        Gate::authorize('create', SalesOrder::class);

        return view('sales-orders.create', $this->options());
    }

    public function store(StoreSalesOrderRequest $r, SaveSalesOrder $save): RedirectResponse
    {
        $o = $save->handle($r->validated(), $r->user()->id);

        return redirect()->route('sales-orders.show', $o);
    }

    public function show(SalesOrder $salesOrder): View
    {
        Gate::authorize('view', $salesOrder);

        return view('sales-orders.show', ['salesOrder' => $salesOrder->load(['quotation', 'customer', 'lines'])]);
    }

    public function edit(SalesOrder $salesOrder): View
    {
        Gate::authorize('update', $salesOrder);

        return view('sales-orders.edit', ['salesOrder' => $salesOrder->load('lines')] + $this->options());
    }

    public function update(UpdateSalesOrderRequest $r, SalesOrder $salesOrder, SaveSalesOrder $save): RedirectResponse
    {
        $save->handle($r->validated(), $r->user()->id, $salesOrder);

        return redirect()->route('sales-orders.show', $salesOrder);
    }

    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        Gate::authorize('delete', $salesOrder);
        $salesOrder->delete();

        return redirect()->route('sales-orders.index');
    }

    public function convert(Quotation $quotation, ConvertQuotationToSalesOrder $convert): RedirectResponse
    {
        Gate::authorize('create', SalesOrder::class);
        $o = $convert->handle($quotation, request()->user()->id);

        return redirect()->route('sales-orders.show', $o);
    }

    public function transition(TransitionSalesOrderRequest $r, SalesOrder $salesOrder, TransitionSalesOrder $transition): RedirectResponse
    {
        $transition->handle($salesOrder, SalesOrderStatus::from($r->validated('status')), $r->user()->id, $r->validated('reason'));

        return back();
    }

    public function fulfill(UpdateSalesOrderFulfillmentRequest $r, SalesOrder $salesOrder, UpdateSalesOrderFulfillment $update): RedirectResponse
    {
        $update->handle($salesOrder, $r->validated('quantities'), $r->user()->id);

        return back();
    }

    private function options(): array
    {
        return ['customers' => Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']), 'items' => ProductService::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name', 'type'])];
    }
}
