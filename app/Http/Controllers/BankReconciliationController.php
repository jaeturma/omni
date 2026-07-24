<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmBankReconciliationMatch;
use App\Actions\CreateBankReconciliation;
use App\Actions\CreateReconciliationAdjustment;
use App\Actions\TransitionBankReconciliation;
use App\Enums\CashTransactionStatus;
use App\Enums\ReconciliationState;
use App\Http\Requests\CreateReconciliationAdjustmentRequest;
use App\Http\Requests\MatchBankReconciliationRequest;
use App\Http\Requests\StoreBankReconciliationRequest;
use App\Http\Requests\TransitionBankReconciliationRequest;
use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Services\BankReconciliationCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', BankReconciliation::class);
        $reconciliations = BankReconciliation::query()->with(['financialAccount:id,code,name'])->latest()->paginate(25);

        return view('bank-reconciliations.index', compact('reconciliations'));
    }

    public function create(): View
    {
        Gate::authorize('create', BankReconciliation::class);
        $imports = BankStatementImport::query()->with('financialAccount:id,code,name')->whereNull('rolled_back_at')->whereNull('finalized_at')
            ->whereDoesntHave('reconciliation')->latest('imported_at')->get();

        return view('bank-reconciliations.create', compact('imports'));
    }

    public function store(StoreBankReconciliationRequest $request, CreateBankReconciliation $action): RedirectResponse
    {
        $reconciliation = $action->handle($request->validated(), $request->user());

        return redirect()->route('bank-reconciliations.show', $reconciliation)->with('success', 'Bank reconciliation created.');
    }

    public function show(BankReconciliation $bankReconciliation, BankReconciliationCalculator $calculator): View
    {
        Gate::authorize('view', $bankReconciliation);
        $bankReconciliation->load(['financialAccount:id,code,name', 'statementImport']);
        $lines = $bankReconciliation->statementImport->lines()->with(['bankStatementImport:id'])->orderBy('line_number')->paginate(25);
        $suggestions = [];
        foreach ($lines->where('match_status', ReconciliationState::Unreconciled) as $line) {
            $suggestions[$line->id] = CashTransaction::query()->where('financial_account_id', $bankReconciliation->financial_account_id)
                ->where('status', CashTransactionStatus::Posted)->whereBetween('transaction_date', [$line->transaction_date->copy()->subDays(3), $line->transaction_date->copy()->addDays(3)])
                ->whereDoesntHave('reconciliationMatch')->get()->filter(function (CashTransaction $transaction) use ($calculator, $line): bool {
                    $amount = $calculator->signed($transaction);

                    return bccomp($amount, '0', 4) === bccomp($line->normalized_amount, '0', 4)
                        && bccomp(str_replace('-', '', $amount), str_replace('-', '', $line->normalized_amount), 4) <= 0;
                })->sortByDesc(fn (CashTransaction $transaction) => [
                    bccomp($calculator->signed($transaction), $line->normalized_amount, 4) === 0,
                    $transaction->reference_number === $line->reference_number,
                ])->take(10);
        }

        return view('bank-reconciliations.show', ['reconciliation' => $bankReconciliation, 'lines' => $lines, 'suggestions' => $suggestions]);
    }

    public function match(MatchBankReconciliationRequest $request, BankReconciliation $bankReconciliation, ConfirmBankReconciliationMatch $action): RedirectResponse
    {
        $line = BankStatementLine::query()->findOrFail($request->integer('bank_statement_line_id'));
        $action->handle($bankReconciliation, $line, $request->validated('cash_transaction_ids'), $request->user());

        return back()->with('success', 'Statement line match confirmed.');
    }

    public function transition(TransitionBankReconciliationRequest $request, BankReconciliation $bankReconciliation, TransitionBankReconciliation $action): RedirectResponse
    {
        $action->handle($bankReconciliation, $request->string('transition')->toString(), $request->input('reason'), $request->user());

        return back()->with('success', 'Reconciliation status updated.');
    }

    public function adjustment(CreateReconciliationAdjustmentRequest $request, BankReconciliation $bankReconciliation, CreateReconciliationAdjustment $action): RedirectResponse
    {
        $line = BankStatementLine::query()->findOrFail($request->integer('bank_statement_line_id'));
        $action->handle($bankReconciliation, $line, $request->string('kind')->toString(), $request->user());

        return back()->with('success', 'Explicit reconciliation adjustment posted.');
    }
}
