<?php

namespace App\Http\Controllers;

use App\Actions\AllocateCustomerPayment;
use App\Actions\SaveCustomerPayment;
use App\Actions\TransitionCustomerPayment;
use App\Enums\CustomerPaymentStatus;
use App\Enums\SalesInvoiceStatus;
use App\Http\Requests\AllocateCustomerPaymentRequest;
use App\Http\Requests\StoreCustomerPaymentRequest;
use App\Http\Requests\TransitionCustomerPaymentRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMethod;
use App\Models\SalesInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerPaymentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', CustomerPayment::class);

        return view('customer-payments.index', ['payments' => CustomerPayment::with(['customer:id,name', 'paymentMethod:id,name'])->latest('payment_date')->paginate(25)]);
    }

    public function create(): View
    {
        Gate::authorize('create', CustomerPayment::class);

        return view('customer-payments.form', $this->formData());
    }

    public function store(StoreCustomerPaymentRequest $request, SaveCustomerPayment $save): RedirectResponse
    {
        $payment = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('customer-payments.show', $payment);
    }

    public function show(CustomerPayment $customerPayment): View
    {
        Gate::authorize('view', $customerPayment);
        $customerPayment->load(['customer', 'paymentMethod', 'bank', 'allocations.salesInvoice']);
        $openInvoices = SalesInvoice::where('customer_id', $customerPayment->customer_id)
            ->whereIn('status', [SalesInvoiceStatus::Posted, SalesInvoiceStatus::PartiallyPaid, SalesInvoiceStatus::Overdue])
            ->oldest('due_date')->get();

        return view('customer-payments.show', compact('customerPayment', 'openInvoices'));
    }

    public function edit(CustomerPayment $customerPayment): View
    {
        Gate::authorize('update', $customerPayment);

        return view('customer-payments.form', $this->formData() + ['customerPayment' => $customerPayment]);
    }

    public function update(StoreCustomerPaymentRequest $request, CustomerPayment $customerPayment, SaveCustomerPayment $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $customerPayment);

        return redirect()->route('customer-payments.show', $customerPayment);
    }

    public function allocate(AllocateCustomerPaymentRequest $request, CustomerPayment $customerPayment, AllocateCustomerPayment $allocate): RedirectResponse
    {
        $allocate->handle($customerPayment, $request->validated('allocations'), $request->user()->id);

        return back();
    }

    public function transition(TransitionCustomerPaymentRequest $request, CustomerPayment $customerPayment, TransitionCustomerPayment $transition): RedirectResponse
    {
        $transition->handle($customerPayment, CustomerPaymentStatus::from($request->validated('status')), $request->user()->id, $request->validated());

        return back();
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return ['customers' => Customer::where('status', 'active')->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::where('status', 'active')->orderBy('name')->get(),
            'banks' => Bank::where('status', 'active')->orderBy('name')->get()];
    }
}
