<?php

namespace App\Http\Controllers;

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\CashFlowClassification;
use App\Enums\CurrentClassification;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Account::class);
        $accounts = Account::query()->with('parent:id,code,name')
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$request->string('search').'%')->orWhere('name', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('account_class'), fn ($query) => $query->where('account_class', $request->string('account_class')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->ordered()->paginate(50)->withQueryString();

        return view('accounts.index', ['accounts' => $accounts, 'accountClasses' => AccountClass::cases()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Account::class);

        return view('accounts.create', $this->formData());
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        Account::query()->create($request->safe()->all());

        return redirect()->route('accounts.index')->with('success', 'Account created.');
    }

    public function edit(Account $account): View
    {
        Gate::authorize('update', $account);

        return view('accounts.edit', $this->formData($account) + ['account' => $account]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->safe()->all());

        return redirect()->route('accounts.index')->with('success', 'Account updated.');
    }

    public function status(Account $account): RedirectResponse
    {
        $ability = $account->is_active ? 'deactivate' : 'activate';
        Gate::authorize($ability, $account);
        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('success', 'Account status updated.');
    }

    /** @return array<string, mixed> */
    private function formData(?Account $excluded = null): array
    {
        return [
            'accountClasses' => AccountClass::cases(),
            'accountTypes' => AccountType::cases(),
            'currentClassifications' => CurrentClassification::cases(),
            'cashFlowClassifications' => CashFlowClassification::cases(),
            'canManageReportingSettings' => Gate::allows('financial-report-settings.manage'),
            'parentAccounts' => Account::query()->when($excluded, fn ($query) => $query->whereKeyNot($excluded->getKey()))->ordered()->get(['id', 'code', 'name', 'account_class']),
        ];
    }
}
