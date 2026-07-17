<?php

namespace App\Http\Controllers;

use App\Actions\SaveGovernmentDeduction;
use App\Actions\TransitionGovernmentDeduction;
use App\Enums\CustomerPaymentStatus;
use App\Enums\GovernmentDeductionStatus;
use App\Enums\SalesInvoiceStatus;
use App\Http\Requests\GovernmentDeductionReportRequest;
use App\Http\Requests\StoreGovernmentDeductionRequest;
use App\Http\Requests\TransitionGovernmentDeductionRequest;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\GovernmentDeduction;
use App\Models\SalesInvoice;
use App\Models\TaxRateSetting;
use App\Reports\GovernmentDeductionSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GovernmentDeductionController extends Controller
{
    public function index(GovernmentDeductionReportRequest $request, GovernmentDeductionSummary $summary): View
    {
        $filters = $request->validated();

        return view('government-deductions.index', ['filters' => $filters, 'deductions' => $summary->paginator($filters),
            'summary' => $summary->totals($filters), 'customers' => Customer::where('type', 'government')->orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', GovernmentDeduction::class);

        return view('government-deductions.form', $this->formData($request));
    }

    public function store(StoreGovernmentDeductionRequest $request, SaveGovernmentDeduction $save): RedirectResponse
    {
        $deduction = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('government-deductions.show', $deduction);
    }

    public function show(GovernmentDeduction $governmentDeduction): View
    {
        Gate::authorize('view', $governmentDeduction);

        return view('government-deductions.show', ['deduction' => $governmentDeduction->load(['customer', 'salesInvoice', 'customerPayment', 'taxRateSetting'])]);
    }

    public function edit(GovernmentDeduction $governmentDeduction): View
    {
        Gate::authorize('update', $governmentDeduction);

        return view('government-deductions.form', $this->formData(request()) + ['deduction' => $governmentDeduction]);
    }

    public function update(StoreGovernmentDeductionRequest $request, GovernmentDeduction $governmentDeduction, SaveGovernmentDeduction $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $governmentDeduction);

        return redirect()->route('government-deductions.show', $governmentDeduction);
    }

    public function transition(TransitionGovernmentDeductionRequest $request, GovernmentDeduction $governmentDeduction, TransitionGovernmentDeduction $transition): RedirectResponse
    {
        $transition->handle($governmentDeduction, GovernmentDeductionStatus::from($request->validated('status')), $request->user()->id, $request->validated());

        return back();
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        $customerIds = Customer::where('type', 'government')->select('id');
        $invoices = SalesInvoice::whereIn('customer_id', $customerIds)->whereIn('status', [SalesInvoiceStatus::Posted, SalesInvoiceStatus::PartiallyPaid, SalesInvoiceStatus::Paid, SalesInvoiceStatus::Overdue])->latest('invoice_date')->get();
        $payments = CustomerPayment::whereIn('customer_id', $customerIds)->whereNotIn('status', [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided])->latest('payment_date')->get();

        return ['invoices' => $invoices, 'payments' => $payments, 'rates' => TaxRateSetting::where('active', true)->latest('effective_from')->get(),
            'selectedInvoice' => $invoices->firstWhere('id', $request->integer('sales_invoice_id'))];
    }
}
