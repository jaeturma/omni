<?php

namespace App\Http\Controllers;

use App\Actions\ImportBankStatement;
use App\Actions\RollbackBankStatementImport;
use App\Http\Requests\ImportBankStatementRequest;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\FinancialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankStatementImportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', BankStatementImport::class);
        $imports = BankStatementImport::query()->with(['financialAccount:id,code,name', 'importer:id,name'])->withCount('lines')
            ->latest('imported_at')->paginate(25);

        return view('bank-statements.index', compact('imports'));
    }

    public function create(): View
    {
        Gate::authorize('create', BankStatementImport::class);
        $accounts = FinancialAccount::query()->where('active', true)->where('allow_reconciliation', true)
            ->whereIn('type', ['bank_checking', 'bank_savings', 'e_wallet'])->orderBy('name')->get(['id', 'code', 'name']);

        return view('bank-statements.create', compact('accounts'));
    }

    public function store(ImportBankStatementRequest $request, ImportBankStatement $action): RedirectResponse
    {
        $import = $action->handle($request->validated(), $request->file('statement_file'), $request->user());

        return redirect()->route('bank-statements.show', $import)->with('success', 'Bank statement imported.');
    }

    public function show(BankStatementImport $bankStatement): View
    {
        Gate::authorize('view', $bankStatement);
        $bankStatement->load(['financialAccount:id,code,name', 'importer:id,name']);
        $lines = $bankStatement->lines()->orderBy('line_number')->paginate(50);

        return view('bank-statements.show', ['import' => $bankStatement, 'lines' => $lines]);
    }

    public function destroy(BankStatementImport $bankStatement, Request $request, RollbackBankStatementImport $action): RedirectResponse
    {
        Gate::authorize('rollback', $bankStatement);
        $action->handle($bankStatement, $request->user());

        return redirect()->route('bank-statements.index')->with('success', 'Bank statement import rolled back.');
    }

    public function export(BankStatementImport $bankStatement): StreamedResponse
    {
        Gate::authorize('export', $bankStatement);

        return response()->streamDownload(function () use ($bankStatement): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['transaction_date', 'posting_date', 'description', 'reference_number', 'debit', 'credit', 'running_balance', 'normalized_amount', 'match_status']);
            foreach (BankStatementLine::query()->whereBelongsTo($bankStatement)->orderBy('line_number')->cursor() as $line) {
                fputcsv($handle, [$line->transaction_date->toDateString(), $line->posting_date->toDateString(), $line->description,
                    $line->reference_number, $line->debit, $line->credit, $line->running_balance, $line->normalized_amount, $line->match_status->value]);
            }
            fclose($handle);
        }, 'bank-statement-'.$bankStatement->id.'.csv', ['Content-Type' => 'text/csv']);
    }
}
