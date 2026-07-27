<x-app-layout title="General Journal">
    <x-page-header title="General Journal" description="Posted journal entries with source traceability." />
    <nav class="mb-5 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('general-ledger.index', request()->query()) }}">General ledger</a>
        <a href="{{ route('general-journal.print', request()->query()) }}">Print</a>
        @can('general-ledger.export')<a href="{{ route('general-journal.export', request()->query()) }}">CSV export</a>@endcan
    </nav>
    <x-ledger-filters :action="route('general-journal.index')" :$filters :$accounts :$sourceTypes :$customers :$suppliers :$financialAccounts :$products :$warehouses />
    <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead><tr><th class="px-4 py-3 text-left">Date</th><th class="text-left">Journal</th><th class="text-left">Source</th><th class="text-left">Reference</th><th class="text-left">Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-left">Status</th></tr></thead>
        <tbody>@forelse($rows as $row)<tr class="border-t"><td class="px-4 py-3">{{ $row->journal_date->toDateString() }}</td><td><a href="{{ route('journal-entries.show', $row) }}">{{ $row->journal_number }}</a></td><td>{{ str($row->source_type->value)->headline() }} @if($row->source_id)#{{ $row->source_id }}@endif</td><td>{{ $row->reference_number ?? '—' }}</td><td>{{ $row->description }}</td><td class="text-right">{{ number_format((float) $row->total_debit, 4) }}</td><td class="text-right">{{ number_format((float) $row->total_credit, 4) }}</td><td>{{ str($row->status->value)->headline() }}@if($row->reverses_entry_id) · Reversal @endif</td></tr>@empty<tr><td colspan="8" class="p-8 text-center">No posted journals for this range.</td></tr>@endforelse</tbody></table>
    </div>
    <div class="mt-5">{{ $rows->links() }}</div>
</x-app-layout>
