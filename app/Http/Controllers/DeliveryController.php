<?php

namespace App\Http\Controllers;

use App\Actions\CreateDelivery;
use App\Actions\TransitionDelivery;
use App\Enums\DeliveryStatus;
use App\Enums\SalesOrderStatus;
use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\TransitionDeliveryRequest;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Delivery::class);
        $deliveries = Delivery::with('salesOrder:id,sales_order_number')->latest('delivery_date')->paginate(25);

        return view('deliveries.index', compact('deliveries'));
    }

    public function create(Request $r): View
    {
        Gate::authorize('create', Delivery::class);
        $orders = SalesOrder::with('lines')->whereIn('status', [SalesOrderStatus::Confirmed, SalesOrderStatus::PartiallyFulfilled])->latest()->get();

        return view('deliveries.create', ['orders' => $orders, 'selectedOrder' => $orders->firstWhere('id', $r->integer('sales_order_id')), 'warehouses' => Warehouse::where('status', 'active')->orderBy('name')->get()]);
    }

    public function store(StoreDeliveryRequest $r, CreateDelivery $create): RedirectResponse
    {
        $d = $create->handle($r->validated(), $r->user()->id);

        return redirect()->route('deliveries.show', $d);
    }

    public function show(Delivery $delivery): View
    {
        Gate::authorize('view', $delivery);

        return view('deliveries.show', ['delivery' => $delivery->load(['salesOrder', 'warehouse', 'lines'])]);
    }

    public function transition(TransitionDeliveryRequest $r, Delivery $delivery, TransitionDelivery $t): RedirectResponse
    {
        $t->handle($delivery, DeliveryStatus::from($r->validated('status')), $r->user()->id, $r->validated());

        return back();
    }

    public function print(Delivery $delivery): View
    {
        Gate::authorize('print', $delivery);

        return view('deliveries.print', ['delivery' => $delivery->load(['salesOrder', 'lines'])]);
    }
}
