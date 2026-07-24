<?php

namespace App\Http\Controllers;

use App\Actions\ChangeFinancialAccountStatus;
use App\Enums\FinancialAccountType;
use App\Http\Requests\StoreFinancialAccountRequest;
use App\Models\Bank;
use App\Models\FinancialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', FinancialAccount::class);
        $accounts = FinancialAccount::query()->with('bank:id,name')->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$request->string('search').'%')->orWhere('name', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))->when($request->filled('status'), fn ($query) => $query->where('active', $request->string('status')->toString() === 'active'))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('financial-accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        Gate::authorize('create', FinancialAccount::class);

        return view('financial-accounts.form', $this->formData());
    }

    public function store(StoreFinancialAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (blank($data['account_number'])) {
            unset($data['account_number']);
        }
        $userId = $request->user()->id;
        DB::transaction(function () use ($data, $userId): void {
            FinancialAccount::query()->create($data + ['opening_balance_set_at' => now(), 'opening_balance_set_by' => $userId,
                'activated_at' => now(), 'activated_by' => $userId, 'created_by' => $userId, 'updated_by' => $userId]);
        });

        return redirect()->route('financial-accounts.index')->with('success', 'Financial account created.');
    }

    public function show(FinancialAccount $financialAccount): View
    {
        Gate::authorize('view', $financialAccount);

        return view('financial-accounts.show', ['account' => $financialAccount->load('bank')]);
    }

    public function edit(FinancialAccount $financialAccount): View
    {
        Gate::authorize('update', $financialAccount);

        return view('financial-accounts.form', $this->formData() + ['account' => $financialAccount]);
    }

    public function update(StoreFinancialAccountRequest $request, FinancialAccount $financialAccount): RedirectResponse
    {
        $data = $request->validated();
        if (blank($data['account_number'])) {
            unset($data['account_number']);
        }
        $userId = $request->user()->id;
        DB::transaction(function () use ($financialAccount, $data, $userId): void {
            $openingBalanceDate = filled($data['opening_balance_date'] ?? null)
                ? Carbon::parse($data['opening_balance_date'])->toDateString()
                : null;

            if (bccomp($financialAccount->opening_balance, (string) $data['opening_balance'], 4) !== 0 || $financialAccount->opening_balance_date?->toDateString() !== $openingBalanceDate) {
                $data['opening_balance_set_at'] = now();
                $data['opening_balance_set_by'] = $userId;
            }
            $financialAccount->update($data + ['updated_by' => $userId]);
        });

        return redirect()->route('financial-accounts.show', $financialAccount)->with('success', 'Financial account updated.');
    }

    public function status(Request $request, FinancialAccount $financialAccount, ChangeFinancialAccountStatus $action): RedirectResponse
    {
        $activate = $request->boolean('active');
        Gate::authorize($activate ? 'activate' : 'deactivate', $financialAccount);
        $request->validate(['active' => ['required', 'boolean'], 'reason' => [Rule::requiredIf(! $activate), 'nullable', 'string', 'max:500']]);
        $action->handle($financialAccount, $activate, $request->input('reason'), $request->user());

        return back()->with('success', $activate ? 'Financial account activated.' : 'Financial account deactivated.');
    }

    private function formData(): array
    {
        return ['types' => FinancialAccountType::cases(), 'banks' => Bank::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])];
    }
}
