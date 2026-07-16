<?php

namespace App\Http\Controllers;

use App\Actions\SaveSalesInvoice;
use App\Actions\TransitionSalesInvoice;
use App\Enums\DeliveryStatus;
use App\Enums\SalesInvoiceStatus;
use App\Http\Requests\StoreSalesInvoiceRequest;
use App\Http\Requests\TransitionSalesInvoiceRequest;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\FiscalPeriod;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SalesInvoice::class);

        return view('sales-invoices.index', ['invoices' => SalesInvoice::latest('invoice_date')->paginate(25)]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SalesInvoice::class);

        return view('sales-invoices.form', $this->formData($request));
    }

    public function store(StoreSalesInvoiceRequest $request, SaveSalesInvoice $save): RedirectResponse
    {
        $invoice = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('sales-invoices.show', $invoice);
    }

    public function show(SalesInvoice $salesInvoice): View
    {
        Gate::authorize('view', $salesInvoice);

        return view('sales-invoices.show', ['invoice' => $salesInvoice->load(['fiscalPeriod', 'salesOrder', 'delivery', 'lines'])]);
    }

    public function edit(SalesInvoice $salesInvoice): View
    {
        Gate::authorize('update', $salesInvoice);

        return view('sales-invoices.form', $this->formData(request()) + ['invoice' => $salesInvoice->load('lines')]);
    }

    public function update(StoreSalesInvoiceRequest $request, SalesInvoice $salesInvoice, SaveSalesInvoice $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $salesInvoice);

        return redirect()->route('sales-invoices.show', $salesInvoice);
    }

    public function transition(TransitionSalesInvoiceRequest $request, SalesInvoice $salesInvoice, TransitionSalesInvoice $transition): RedirectResponse
    {
        $transition->handle($salesInvoice, SalesInvoiceStatus::from($request->validated('status')), $request->user()->id, $request->validated());

        return back();
    }

    public function print(SalesInvoice $salesInvoice): View
    {
        Gate::authorize('print', $salesInvoice);

        return view('sales-invoices.print', ['invoice' => $salesInvoice->load('lines')]);
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        $orders = SalesOrder::with('lines')->whereNotIn('status', ['draft', 'cancelled'])->latest()->get();
        $deliveries = Delivery::with(['lines.salesOrderLine'])->whereIn('status', [DeliveryStatus::Released, DeliveryStatus::Delivered, DeliveryStatus::Accepted])->latest()->get();

        return ['customers' => Customer::where('status', 'active')->orderBy('name')->get(),
            'periods' => FiscalPeriod::where('status', 'open')->oldest('starts_on')->get(), 'orders' => $orders, 'deliveries' => $deliveries,
            'selectedOrder' => $orders->firstWhere('id', $request->integer('sales_order_id')),
            'selectedDelivery' => $deliveries->firstWhere('id', $request->integer('delivery_id'))];
    }
}
