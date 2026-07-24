<?php

namespace App\Http\Controllers;

use App\Actions\TransitionFundTransfer;
use App\Enums\FundTransferStatus;
use App\Http\Requests\StoreFundTransferRequest;
use App\Http\Requests\TransitionFundTransferRequest;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\FundTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FundTransferController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', FundTransfer::class);

        return view('fund-transfers.index', [
            'transfers' => FundTransfer::query()
                ->with(['sourceFinancialAccount:id,code,name', 'destinationFinancialAccount:id,code,name'])
                ->latest('transfer_date')->latest('id')->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', FundTransfer::class);

        return view('fund-transfers.create', [
            'periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'accounts' => FinancialAccount::query()->where('active', true)->where('allow_transfers', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreFundTransferRequest $request): RedirectResponse
    {
        $transfer = DB::transaction(fn () => FundTransfer::query()->create($request->validated() + [
            'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
        ]));

        return redirect()->route('fund-transfers.show', $transfer)->with('success', 'Fund transfer draft saved.');
    }

    public function show(FundTransfer $fundTransfer): View
    {
        Gate::authorize('view', $fundTransfer);

        return view('fund-transfers.show', [
            'transfer' => $fundTransfer->load(['sourceFinancialAccount', 'destinationFinancialAccount', 'sourceTransaction', 'destinationTransaction', 'fiscalPeriod']),
        ]);
    }

    public function transition(TransitionFundTransferRequest $request, FundTransfer $fundTransfer, TransitionFundTransfer $transition): RedirectResponse
    {
        $transition->handle($fundTransfer, FundTransferStatus::from($request->validated('status')), $request->user()->id, $request->validated());

        return back()->with('success', 'Fund transfer status updated.');
    }
}
