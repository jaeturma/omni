<x-app-layout title="Bank Reconciliations">
    <x-page-header title="Bank Reconciliations" description="Confirmed statement-to-system matches and transparent reconciliation differences." />
    <div class="mb-5 flex justify-end">@can('create', \App\Models\BankReconciliation::class)<a href="{{ route('bank-reconciliations.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Start reconciliation</a>@endcan</div>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Account</th><th>Period ending</th><th class="text-right">Statement closing</th><th class="text-right">System closing</th><th class="text-right">Difference</th><th>Status</th></tr></thead>
            <tbody>@forelse($reconciliations as $reconciliation)<tr class="border-t"><td class="px-5 py-3"><a class="font-semibold text-blue-700" href="{{ route('bank-reconciliations.show', $reconciliation) }}">{{ $reconciliation->financialAccount->code }}</a></td><td class="text-center">{{ $reconciliation->statement_end_date->format('M d, Y') }}</td><td class="text-right">{{ number_format((float) $reconciliation->statement_closing_balance, 2) }}</td><td class="text-right">{{ number_format((float) $reconciliation->system_closing_balance, 2) }}</td><td class="text-right">{{ number_format((float) $reconciliation->reconciliation_difference, 2) }}</td><td class="text-center capitalize">{{ $reconciliation->status->value }}</td></tr>@empty<tr><td colspan="6" class="p-8 text-center">No bank reconciliations found.</td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="mt-5">{{ $reconciliations->links() }}</div>
</x-app-layout>
