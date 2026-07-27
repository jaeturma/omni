<x-app-layout title="Trial Balance">
    <x-page-header title="Trial Balance" description="Unadjusted or adjusted balances from posted journals, by period or custom date range." />
    <nav class="mb-5 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('subledger-reconciliations.index', request()->query()) }}">Subledger reconciliation</a>
        <a href="{{ route('trial-balance.print', request()->query()) }}">Print</a>
        @can('trial-balance.export')<a href="{{ route('trial-balance.export', request()->query()) }}">CSV export</a>@endcan
    </nav>
    <x-trial-balance-filters :action="route('trial-balance.index')" :$filters :$periods :$accounts trial-balance />
    <div class="mt-6 flex items-center justify-between gap-4 rounded-xl p-4 ring-1 {{ $balanced ? 'bg-emerald-50 ring-emerald-200' : 'bg-red-50 ring-red-200' }}">
        <span class="font-semibold">{{ $balanced ? 'Balanced' : 'Out of balance' }}</span>
        <span class="text-sm">Closing debits PHP {{ number_format((float) $totals['closing_debit'], 4) }} · Closing credits PHP {{ number_format((float) $totals['closing_credit'], 4) }}</span>
    </div>
    <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead><tr><th rowspan="2" class="px-4 py-3 text-left">Account</th><th colspan="2">Opening</th><th colspan="2">Movement</th><th colspan="2">Closing</th></tr><tr><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Debit</th><th class="pr-4 text-right">Credit</th></tr></thead>
        <tbody>@forelse($rows as $row)<tr class="border-t {{ $row['is_header'] ? 'bg-slate-50 font-semibold' : '' }}"><td class="px-4 py-3">{{ $row['account']->code }} — {{ $row['account']->name }}</td>@foreach(['opening_debit', 'opening_credit', 'movement_debit', 'movement_credit', 'closing_debit', 'closing_credit'] as $key)<td class="{{ $loop->last ? 'pr-4 ' : '' }}text-right">{{ number_format((float) $row[$key], 4) }}</td>@endforeach</tr>@empty<tr><td colspan="7" class="p-8 text-center">No posted balances for this selection.</td></tr>@endforelse</tbody>
        <tfoot><tr class="border-t-2 font-bold"><td class="px-4 py-3">Postable-account totals</td>@foreach(['opening_debit', 'opening_credit', 'movement_debit', 'movement_credit', 'closing_debit', 'closing_credit'] as $key)<td class="{{ $loop->last ? 'pr-4 ' : '' }}text-right">{{ number_format((float) $totals[$key], 4) }}</td>@endforeach</tr></tfoot></table>
    </div>
    <div class="mt-5">{{ $rows->links() }}</div>
</x-app-layout>
