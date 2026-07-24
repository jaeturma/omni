<?php

namespace App\Http\Controllers;

use App\Actions\ReplenishPettyCash;
use App\Actions\TransitionPettyCashVoucher;
use App\Enums\FinancialAccountType;
use App\Enums\PettyCashVoucherStatus;
use App\Http\Requests\StorePettyCashFundRequest;
use App\Http\Requests\StorePettyCashReplenishmentRequest;
use App\Http\Requests\StorePettyCashVoucherRequest;
use App\Http\Requests\TransitionPettyCashVoucherRequest;
use App\Http\Requests\UpdatePettyCashFundRequest;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\PettyCashFund;
use App\Models\PettyCashVoucher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PettyCashController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PettyCashFund::class);

        return view('petty-cash.index', [
            'funds' => PettyCashFund::query()->with(['financialAccount:id,code,name', 'custodian:id,name'])->latest()->get(),
            'vouchers' => PettyCashVoucher::query()->with(['fund.financialAccount:id,code,name'])->latest('voucher_date')->latest('id')->paginate(25),
        ]);
    }

    public function createFund(): View
    {
        Gate::authorize('create', PettyCashFund::class);

        return view('petty-cash.create-fund', [
            'accounts' => FinancialAccount::query()->where('type', FinancialAccountType::PettyCash)->where('active', true)
                ->whereDoesntHave('pettyCashFund')->orderBy('name')->get(),
            'custodians' => User::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeFund(StorePettyCashFundRequest $request): RedirectResponse
    {
        $account = FinancialAccount::findOrFail($request->integer('financial_account_id'));
        $fund = DB::transaction(function () use ($account, $request): PettyCashFund {
            $account->update([
                'allow_receipts' => false, 'allow_disbursements' => false, 'allow_transfers' => false,
                'updated_by' => $request->user()->id,
            ]);

            return PettyCashFund::query()->create($request->validated() + [
                'current_operational_balance' => $account->current_balance ?? $account->opening_balance,
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('petty-cash.funds.show', $fund)->with('success', 'Petty-cash fund created.');
    }

    public function showFund(PettyCashFund $pettyCashFund): View
    {
        Gate::authorize('view', $pettyCashFund);

        return view('petty-cash.show-fund', [
            'fund' => $pettyCashFund->load(['financialAccount', 'custodian']),
            'eligibleVouchers' => $pettyCashFund->vouchers()->where('status', PettyCashVoucherStatus::Liquidated)
                ->whereDoesntHave('replenishments')->latest('voucher_date')->get(),
            'sourceAccounts' => FinancialAccount::query()->where('active', true)->where('allow_transfers', true)
                ->whereKeyNot($pettyCashFund->financial_account_id)->orderBy('name')->get(),
            'custodians' => User::query()->where('active', true)->orderBy('name')->get(),
            'periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'replenishments' => $pettyCashFund->replenishments()->with('sourceFinancialAccount:id,code,name')->latest('replenishment_date')->get(),
        ]);
    }

    public function updateFund(UpdatePettyCashFundRequest $request, PettyCashFund $pettyCashFund): RedirectResponse
    {
        $pettyCashFund->update($request->validated() + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Petty-cash fund updated.');
    }

    public function createVoucher(): View
    {
        Gate::authorize('create', PettyCashVoucher::class);

        return view('petty-cash.create-voucher', [
            'funds' => PettyCashFund::query()->with('financialAccount:id,code,name')->where('active', true)->orderBy('id')->get(),
            'periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function storeVoucher(StorePettyCashVoucherRequest $request): RedirectResponse
    {
        $voucher = DB::transaction(fn () => PettyCashVoucher::query()->create($request->validated() + [
            'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
        ]));

        return redirect()->route('petty-cash.vouchers.show', $voucher)->with('success', 'Petty-cash voucher draft saved.');
    }

    public function showVoucher(PettyCashVoucher $pettyCashVoucher): View
    {
        Gate::authorize('view', $pettyCashVoucher);

        return view('petty-cash.show-voucher', [
            'voucher' => $pettyCashVoucher->load(['fund.financialAccount', 'expense', 'transactions', 'replenishments', 'fiscalPeriod']),
            'expenses' => Expense::query()->whereIn('status', ['approved', 'paid', 'reimbursable'])
                ->whereDoesntHave('pettyCashVoucher')->latest('expense_date')->get(),
        ]);
    }

    public function transition(TransitionPettyCashVoucherRequest $request, PettyCashVoucher $pettyCashVoucher, TransitionPettyCashVoucher $transition): RedirectResponse
    {
        $transition->handle($pettyCashVoucher, PettyCashVoucherStatus::from($request->validated('status')), $request->user()->id, $request->validated());

        return back()->with('success', 'Petty-cash voucher status updated.');
    }

    public function replenish(StorePettyCashReplenishmentRequest $request, ReplenishPettyCash $replenish): RedirectResponse
    {
        $replenish->handle($request->validated(), $request->user()->id);

        return back()->with('success', 'Petty-cash fund replenished.');
    }
}
