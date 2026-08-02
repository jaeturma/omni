<?php

namespace App\Http\Controllers;

use App\Actions\SaveSupplierInvoice;
use App\Actions\TransitionSupplierInvoice;
use App\Enums\ReceivingStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Http\Requests\StoreSupplierInvoiceRequest;
use App\Http\Requests\TransitionSupplierInvoiceRequest;
use App\Http\Requests\UpdateSupplierInvoiceRequest;
use App\Models\FiscalPeriod;
use App\Models\ReceivingRecord;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Support\SensitiveData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SupplierInvoice::class);
        $invoices = SupplierInvoice::query()->with(['supplier:id,name', 'fiscalPeriod:id,name'])->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($nested) => $nested->where('internal_number', 'like', '%'.$request->string('search').'%')->orWhere('supplier_invoice_number', 'like', '%'.$request->string('search').'%')->orWhere('supplier_name', 'like', '%'.$request->string('search').'%')))->when($request->status, fn ($query, $status) => $query->where('status', $status))->latest('id')->paginate(20)->withQueryString();

        return view('supplier-invoices.index', compact('invoices'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SupplierInvoice::class);
        $receivingRecord = $request->integer('receiving_record_id') ? ReceivingRecord::query()->with(['supplier', 'lines.purchaseOrderLine'])->whereIn('status', [ReceivingStatus::Accepted, ReceivingStatus::PartiallyAccepted])->findOrFail($request->integer('receiving_record_id')) : null;

        return view('supplier-invoices.create', ['suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(), 'periods' => FiscalPeriod::query()->where('status', 'open')->orderBy('starts_on')->get(), 'receivingRecord' => $receivingRecord]);
    }

    public function store(StoreSupplierInvoiceRequest $request, SaveSupplierInvoice $save): RedirectResponse
    {
        $invoice = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('supplier-invoices.show', $invoice)->with('status', 'Supplier invoice draft saved.');
    }

    public function show(SupplierInvoice $supplierInvoice): View
    {
        Gate::authorize('view', $supplierInvoice);
        $supplierInvoice->setAttribute('supplier_tin', SensitiveData::mask($supplierInvoice->supplier_tin, 4, '—'));

        return view('supplier-invoices.show', ['invoice' => $supplierInvoice->load(['supplier', 'purchaseOrder', 'receivingRecord', 'fiscalPeriod', 'lines'])]);
    }

    public function edit(SupplierInvoice $supplierInvoice): View
    {
        Gate::authorize('update', $supplierInvoice);

        return view('supplier-invoices.edit', ['invoice' => $supplierInvoice->load('lines'), 'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(), 'periods' => FiscalPeriod::query()->where('status', 'open')->orderBy('starts_on')->get()]);
    }

    public function update(UpdateSupplierInvoiceRequest $request, SupplierInvoice $supplierInvoice, SaveSupplierInvoice $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $supplierInvoice);

        return redirect()->route('supplier-invoices.show', $supplierInvoice)->with('status', 'Supplier invoice draft updated.');
    }

    public function destroy(SupplierInvoice $supplierInvoice): RedirectResponse
    {
        Gate::authorize('delete', $supplierInvoice);
        $supplierInvoice->delete();

        return redirect()->route('supplier-invoices.index')->with('status', 'Supplier invoice draft deleted.');
    }

    public function transition(TransitionSupplierInvoiceRequest $request, SupplierInvoice $supplierInvoice, TransitionSupplierInvoice $transition): RedirectResponse
    {
        $transition->handle($supplierInvoice, SupplierInvoiceStatus::from($request->validated('status')), $request->user()->id, $request->validated('reason'));

        return back()->with('status', 'Supplier invoice status updated.');
    }

    public function print(SupplierInvoice $supplierInvoice): View
    {
        Gate::authorize('print', $supplierInvoice);

        return view('supplier-invoices.print', ['invoice' => $supplierInvoice->load(['supplier', 'fiscalPeriod', 'lines'])]);
    }
}
