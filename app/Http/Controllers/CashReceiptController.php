<?php

namespace App\Http\Controllers;

use App\Actions\TransitionCashReceipt;
use App\Enums\CashReceiptSourceType;
use App\Enums\CashReceiptStatus;
use App\Http\Requests\StoreCashReceiptRequest;
use App\Http\Requests\TransitionCashReceiptRequest;
use App\Models\CashReceipt;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CashReceiptController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', CashReceipt::class);

        return view('cash-receipts.index', ['receipts' => CashReceipt::query()->with(['financialAccount:id,code,name', 'customer:id,name', 'paymentMethod:id,name'])->latest('receipt_date')->latest('id')->paginate(25)]);
    }

    public function create(): View
    {
        Gate::authorize('create', CashReceipt::class);

        return view('cash-receipts.form', $this->formData());
    }

    public function store(StoreCashReceiptRequest $request): RedirectResponse
    {
        $receipt = DB::transaction(fn () => CashReceipt::query()->create($request->validated() + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]));

        return redirect()->route('cash-receipts.show', $receipt)->with('success', 'Cash receipt draft saved.');
    }

    public function show(CashReceipt $cashReceipt): View
    {
        Gate::authorize('view', $cashReceipt);

        return view('cash-receipts.show', ['receipt' => $cashReceipt->load(['financialAccount', 'customer', 'customerPayment', 'paymentMethod', 'fiscalPeriod'])]);
    }

    public function edit(CashReceipt $cashReceipt): View
    {
        Gate::authorize('update', $cashReceipt);

        return view('cash-receipts.form', $this->formData() + ['receipt' => $cashReceipt]);
    }

    public function update(StoreCashReceiptRequest $request, CashReceipt $cashReceipt): RedirectResponse
    {
        $cashReceipt->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('cash-receipts.show', $cashReceipt)->with('success', 'Cash receipt updated.');
    }

    public function transition(TransitionCashReceiptRequest $request, CashReceipt $cashReceipt, TransitionCashReceipt $transition): RedirectResponse
    {
        $transition->handle($cashReceipt, CashReceiptStatus::from($request->validated('status')), $request->user()->id, $request->validated());

        return back()->with('success', 'Cash receipt status updated.');
    }

    public function print(CashReceipt $cashReceipt): View
    {
        Gate::authorize('print', $cashReceipt);

        return view('cash-receipts.print', ['receipt' => $cashReceipt->load(['financialAccount', 'customer', 'paymentMethod'])]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return ['sourceTypes' => CashReceiptSourceType::cases(), 'periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'accounts' => FinancialAccount::query()->where('active', true)->where('allow_receipts', true)->orderBy('name')->get(),
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(),
            'payments' => CustomerPayment::query()->with('customer:id,name')->whereNotIn('status', ['draft', 'voided'])->whereDoesntHave('cashReceipt')->latest('payment_date')->get(),
            'paymentMethods' => PaymentMethod::query()->where('status', 'active')->orderBy('name')->get()];
    }
}
