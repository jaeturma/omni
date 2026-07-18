<?php

namespace App\Http\Controllers;

use App\Actions\AllocateSupplierPayment;
use App\Actions\SaveSupplierPayment;
use App\Actions\TransitionSupplierPayment;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Http\Requests\AllocateSupplierPaymentRequest;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Http\Requests\TransitionSupplierPaymentRequest;
use App\Models\Bank;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SupplierPayment::class);

        return view('supplier-payments.index', ['payments' => SupplierPayment::query()->with(['supplier:id,name', 'paymentMethod:id,name'])->latest('payment_date')->paginate(25)]);
    }

    public function create(): View
    {
        Gate::authorize('create', SupplierPayment::class);

        return view('supplier-payments.form', $this->formData());
    }

    public function store(StoreSupplierPaymentRequest $request, SaveSupplierPayment $save): RedirectResponse
    {
        $payment = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('supplier-payments.show', $payment);
    }

    public function show(SupplierPayment $supplierPayment): View
    {
        Gate::authorize('view', $supplierPayment);
        $supplierPayment->load(['supplier', 'paymentMethod', 'bank', 'allocations.supplierInvoice']);
        $openInvoices = SupplierInvoice::query()->where('supplier_id', $supplierPayment->supplier_id)->whereIn('status', [SupplierInvoiceStatus::Posted, SupplierInvoiceStatus::PartiallyPaid, SupplierInvoiceStatus::Overdue])->oldest('due_date')->get();

        return view('supplier-payments.show', compact('supplierPayment', 'openInvoices'));
    }

    public function edit(SupplierPayment $supplierPayment): View
    {
        Gate::authorize('update', $supplierPayment);

        return view('supplier-payments.form', $this->formData() + compact('supplierPayment'));
    }

    public function update(StoreSupplierPaymentRequest $request, SupplierPayment $supplierPayment, SaveSupplierPayment $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $supplierPayment);

        return redirect()->route('supplier-payments.show', $supplierPayment);
    }

    public function destroy(SupplierPayment $supplierPayment): RedirectResponse
    {
        Gate::authorize('delete', $supplierPayment);
        $supplierPayment->delete();

        return redirect()->route('supplier-payments.index');
    }

    public function allocate(AllocateSupplierPaymentRequest $request, SupplierPayment $supplierPayment, AllocateSupplierPayment $allocate): RedirectResponse
    {
        $allocate->handle($supplierPayment, $request->validated('allocations'), $request->user()->id);

        return back();
    }

    public function transition(TransitionSupplierPaymentRequest $request, SupplierPayment $supplierPayment, TransitionSupplierPayment $transition): RedirectResponse
    {
        $transition->handle($supplierPayment, SupplierPaymentStatus::from($request->validated('status')), $request->user()->id, $request->validated('reason'));

        return back();
    }

    public function print(SupplierPayment $supplierPayment): View
    {
        Gate::authorize('print', $supplierPayment);

        return view('supplier-payments.print', ['payment' => $supplierPayment->load(['supplier', 'paymentMethod', 'bank', 'allocations.supplierInvoice'])]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return ['suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(), 'paymentMethods' => PaymentMethod::query()->where('status', 'active')->orderBy('name')->get(), 'banks' => Bank::query()->where('status', 'active')->orderBy('name')->get()];
    }
}
