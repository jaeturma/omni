<x-app-layout title="Bank Reconciliation">
    <x-page-header title="{{ $reconciliation->financialAccount->code }} Reconciliation" description="{{ $reconciliation->statement_start_date->format('M d, Y') }} to {{ $reconciliation->statement_end_date->format('M d, Y') }}" />
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach(['Statement closing' => $reconciliation->statement_closing_balance, 'System closing' => $reconciliation->system_closing_balance, 'Unmatched deposits' => $reconciliation->unmatched_deposits, 'Unmatched withdrawals' => $reconciliation->unmatched_withdrawals, 'Bank charges' => $reconciliation->bank_charges, 'Interest / other' => $reconciliation->interest_other_items, 'Difference' => $reconciliation->reconciliation_difference] as $label => $value)
            <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200"><div class="text-xs text-slate-500">{{ $label }}</div><div class="text-lg font-semibold">PHP {{ number_format((float) $value, 2) }}</div></div>
        @endforeach
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200"><div class="text-xs text-slate-500">Status</div><div class="text-lg font-semibold capitalize">{{ $reconciliation->status->value }}</div></div>
    </div>
    <div class="my-5 flex flex-wrap gap-3">
        @can('finalize', $reconciliation)
            @if(in_array($reconciliation->status->value, ['draft', 'reopened']))<form method="POST" action="{{ route('bank-reconciliations.transition', $reconciliation) }}">@csrf @method('PATCH')<input type="hidden" name="transition" value="review"><button class="rounded-lg border bg-white px-4 py-2 font-semibold">Mark reviewed</button></form>@endif
            @if($reconciliation->status->value === 'reviewed')<form method="POST" action="{{ route('bank-reconciliations.transition', $reconciliation) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="transition" value="finalize"><input name="reason" placeholder="Required only for non-zero exception" class="rounded-lg border-slate-300"><button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Finalize</button></form>@endif
        @endcan
        @can('reopen', $reconciliation)<form method="POST" action="{{ route('bank-reconciliations.transition', $reconciliation) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="transition" value="reopen"><input name="reason" required placeholder="Reopening reason" class="rounded-lg border-slate-300"><button class="rounded-lg border border-amber-400 bg-white px-4 py-2 font-semibold">Reopen</button></form>@endcan
    </div>
    <div class="grid gap-4">
        @foreach($lines as $line)
            <section class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
                <div class="flex flex-wrap justify-between gap-3"><div><span class="font-semibold">{{ $line->transaction_date->toDateString() }} · {{ $line->description }}</span><div class="text-sm text-slate-500">{{ $line->reference_number ?? 'No reference' }}</div></div><div class="text-right font-semibold">{{ number_format((float) $line->normalized_amount, 2) }}<div class="text-xs capitalize text-slate-500">{{ $line->match_status->value }}</div></div></div>
                @can('match', $reconciliation)
                    @if($line->match_status === \App\Enums\ReconciliationState::Unreconciled)
                        <form method="POST" action="{{ route('bank-reconciliations.matches.store', $reconciliation) }}" class="mt-3 grid gap-2">@csrf<input type="hidden" name="bank_statement_line_id" value="{{ $line->id }}">
                            @forelse($suggestions[$line->id] ?? [] as $transaction)<label class="flex gap-2 text-sm"><input type="checkbox" name="cash_transaction_ids[]" value="{{ $transaction->id }}"> {{ $transaction->transaction_date->toDateString() }} · {{ $transaction->reference_number ?? 'No reference' }} · {{ number_format((float) $transaction->amount, 2) }}</label>@empty<span class="text-sm text-slate-500">No amount/date candidates were found within ±3 days.</span>@endforelse
                            <button class="w-fit rounded-lg border bg-white px-3 py-2 text-sm font-semibold">Confirm selected match</button>
                        </form>
                        <form method="POST" action="{{ route('bank-reconciliations.adjustments.store', $reconciliation) }}" class="mt-3 flex flex-wrap gap-2">@csrf<input type="hidden" name="bank_statement_line_id" value="{{ $line->id }}"><select name="kind" class="rounded-lg border-slate-300"><option value="bank_charge">Bank charge</option><option value="interest_other">Interest / other item</option></select><button class="rounded-lg border border-amber-400 bg-white px-3 py-2 text-sm font-semibold">Create explicit adjustment</button></form>
                    @endif
                @endcan
            </section>
        @endforeach
    </div>
    <div class="mt-5">{{ $lines->links() }}</div>
</x-app-layout>
