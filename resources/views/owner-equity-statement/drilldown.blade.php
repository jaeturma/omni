<x-app-layout :title="$label">
    <x-page-header :title="$label" description="Posted journal lines supporting this owner's-equity activity." />
    <nav class="mb-5 text-sm"><a href="{{ route('owner-equity-statement.index', $filters) }}">Back to owner's equity statement</a></nav>
    <div class="rounded-xl bg-slate-50 p-4 text-sm ring-1 ring-slate-200">{{ $filters['start_date'] }} to {{ $filters['end_date'] }} · Fiscal period {{ $filters['fiscal_period_id'] ?? 'Custom' }} · Total PHP {{ $display_total }}</div>
    <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Journal</th><th class="px-4 py-3 text-left">Reference</th><th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-right">Debit</th><th class="px-4 py-3 text-right">Credit</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="border-t"><td class="px-4 py-3">{{ $row->journalEntry->journal_date->toDateString() }}</td><td class="px-4 py-3">@if($rowLinks[$row->id]['journal_url'])<a href="{{ $rowLinks[$row->id]['journal_url'] }}">{{ $row->journalEntry->journal_number }}</a>@else{{ $row->journalEntry->journal_number }}@endif</td><td class="px-4 py-3">{{ $row->journalEntry->reference_number }} @if($rowLinks[$row->id]['source_url'])· <a href="{{ $rowLinks[$row->id]['source_url'] }}">{{ $rowLinks[$row->id]['source_label'] }}</a>@endif</td><td class="px-4 py-3">{{ $row->account->code }} — {{ $row->account->name }}</td><td class="px-4 py-3 text-right">{{ $row->debit }}</td><td class="px-4 py-3 text-right">{{ $row->credit }}</td></tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">No posted activity for this selection.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $rows->links() }}</div>
</x-app-layout>
