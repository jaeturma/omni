<?php

namespace App\Http\Controllers;

use App\Actions\TransitionCashDisbursement;
use App\Enums\CashDisbursementSourceType;
use App\Enums\CashDisbursementStatus;
use App\Http\Requests\StoreCashDisbursementRequest;
use App\Http\Requests\TransitionCashDisbursementRequest;
use App\Models\CashDisbursement;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\PaymentMethod;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CashDisbursementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', CashDisbursement::class);

        return view('cash-disbursements.index', [
            'disbursements' => CashDisbursement::query()
                ->with(['financialAccount:id,code,name', 'paymentMethod:id,name'])
                ->latest('disbursement_date')->latest('id')->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', CashDisbursement::class);

        return view('cash-disbursements.form', $this->formData());
    }

    public function store(StoreCashDisbursementRequest $request): RedirectResponse
    {
        $disbursement = DB::transaction(fn () => CashDisbursement::query()->create($request->validated() + [
            'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
        ]));

        return redirect()->route('cash-disbursements.show', $disbursement)->with('success', 'Cash disbursement draft saved.');
    }

    public function show(CashDisbursement $cashDisbursement): View
    {
        Gate::authorize('view', $cashDisbursement);

        return view('cash-disbursements.show', [
            'disbursement' => $cashDisbursement->load(['financialAccount', 'supplierPayment.supplier', 'expense', 'paymentMethod', 'fiscalPeriod']),
        ]);
    }

    public function edit(CashDisbursement $cashDisbursement): View
    {
        Gate::authorize('update', $cashDisbursement);

        return view('cash-disbursements.form', $this->formData() + ['disbursement' => $cashDisbursement]);
    }

    public function update(StoreCashDisbursementRequest $request, CashDisbursement $cashDisbursement): RedirectResponse
    {
        $cashDisbursement->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('cash-disbursements.show', $cashDisbursement)->with('success', 'Cash disbursement updated.');
    }

    public function transition(TransitionCashDisbursementRequest $request, CashDisbursement $cashDisbursement, TransitionCashDisbursement $transition): RedirectResponse
    {
        $transition->handle($cashDisbursement, CashDisbursementStatus::from($request->validated('status')), $request->user()->id, $request->validated());

        return back()->with('success', 'Cash disbursement status updated.');
    }

    public function print(CashDisbursement $cashDisbursement): View
    {
        Gate::authorize('print', $cashDisbursement);

        return view('cash-disbursements.print', [
            'disbursement' => $cashDisbursement->load(['financialAccount', 'supplierPayment.supplier', 'expense', 'paymentMethod']),
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'sourceTypes' => CashDisbursementSourceType::cases(),
            'periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'accounts' => FinancialAccount::query()->where('active', true)->where('allow_disbursements', true)->orderBy('name')->get(),
            'supplierPayments' => SupplierPayment::query()->with('supplier:id,name')->whereNotIn('status', ['draft', 'voided'])
                ->whereDoesntHave('cashDisbursement')->latest('payment_date')->get(),
            'expenses' => Expense::query()->whereIn('status', ['approved', 'paid', 'reimbursable'])
                ->whereDoesntHave('cashDisbursement')->latest('expense_date')->get(),
            'paymentMethods' => PaymentMethod::query()->where('status', 'active')->orderBy('name')->get(),
        ];
    }
}
