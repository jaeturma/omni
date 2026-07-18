<?php

namespace App\Http\Controllers;

use App\Actions\SaveExpense;
use App\Actions\TransitionExpense;
use App\Enums\ExpenseStatus;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\TransitionExpenseRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Expense::class);
        $expenses = Expense::query()->with(['supplier:id,name', 'fiscalPeriod:id,name'])->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($nested) => $nested->where('expense_number', 'like', '%'.$request->string('search').'%')->orWhere('payee_name', 'like', '%'.$request->string('search').'%')->orWhere('description', 'like', '%'.$request->string('search').'%')))->when($request->status, fn ($query, $status) => $query->where('status', $status))->latest('expense_date')->paginate(25)->withQueryString();

        return view('expenses.index', compact('expenses'));
    }

    public function create(): View
    {
        Gate::authorize('create', Expense::class);

        return view('expenses.form', $this->formData());
    }

    public function store(StoreExpenseRequest $request, SaveExpense $save): RedirectResponse
    {
        $expense = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('expenses.show', $expense);
    }

    public function show(Expense $expense): View
    {
        Gate::authorize('view', $expense);

        return view('expenses.show', ['expense' => $expense->load(['fiscalPeriod', 'supplier', 'customer', 'paymentMethod', 'bank']), 'paymentMethods' => PaymentMethod::query()->where('status', 'active')->orderBy('name')->get(), 'banks' => Bank::query()->where('status', 'active')->orderBy('name')->get()]);
    }

    public function edit(Expense $expense): View
    {
        Gate::authorize('update', $expense);

        return view('expenses.form', $this->formData() + compact('expense'));
    }

    public function update(StoreExpenseRequest $request, Expense $expense, SaveExpense $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $expense);

        return redirect()->route('expenses.show', $expense);
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);
        $expense->delete();

        return redirect()->route('expenses.index');
    }

    public function transition(TransitionExpenseRequest $request, Expense $expense, TransitionExpense $transition): RedirectResponse
    {
        $transition->handle($expense, ExpenseStatus::from($request->validated('status')), $request->user()->id, $request->validated());

        return back();
    }

    public function print(Expense $expense): View
    {
        Gate::authorize('print', $expense);

        return view('expenses.print', ['expense' => $expense->load(['fiscalPeriod', 'supplier', 'customer', 'paymentMethod', 'bank'])]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return ['periods' => FiscalPeriod::query()->where('status', 'open')->orderBy('starts_on')->get(), 'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(), 'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(), 'paymentMethods' => PaymentMethod::query()->where('status', 'active')->orderBy('name')->get(), 'banks' => Bank::query()->where('status', 'active')->orderBy('name')->get()];
    }
}
