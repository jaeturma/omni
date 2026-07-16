<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Models\Bank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BankController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Bank::class);
        $banks = Bank::query()->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$request->string('search').'%')->orWhere('name', 'like', '%'.$request->string('search').'%')->orWhere('swift_code', 'like', '%'.$request->string('search').'%')))->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))->orderBy('name')->paginate(25)->withQueryString();

        return view('banks.index', ['banks' => $banks]);
    }

    public function create(): View
    {
        Gate::authorize('create', Bank::class);

        return view('banks.create');
    }

    public function store(StoreBankRequest $request): RedirectResponse
    {
        Bank::query()->create($request->validated() + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('banks.index')->with('success', 'Bank created.');
    }

    public function edit(Bank $bank): View
    {
        Gate::authorize('update', $bank);

        return view('banks.edit', ['bank' => $bank]);
    }

    public function update(UpdateBankRequest $request, Bank $bank): RedirectResponse
    {
        $bank->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('banks.index')->with('success', 'Bank updated.');
    }

    public function destroy(Bank $bank): RedirectResponse
    {
        Gate::authorize('delete', $bank);
        $bank->delete();

        return redirect()->route('banks.index')->with('success', 'Bank deleted.');
    }
}
