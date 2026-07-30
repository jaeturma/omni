<x-app-layout title="Income Statement Drilldown">
    <x-page-header :title="$account->code.' — '.$account->name" description="Posted journal lines supporting this income-statement amount." />
    <nav class="mb-5 text-sm"><a href="{{ route('income-statement.index', $filters) }}">Back to income statement</a></nav>
    <div class="rounded-xl bg-slate-50 p-4 text-sm ring-1 ring-slate-200">
        {{ $filters['start_date'] }} to {{ $filters['end_date'] }} · As of {{ $filters['as_of'] }} · Fiscal period {{ $filters['fiscal_period_id'] ?? 'Custom' }} · {{ str($filters['report_view'])->headline() }} · Total PHP {{ $display_total }}
    </div>
    <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Journal</th><th class="px-4 py-3 text-left">Reference</th><th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-right">Debit</th><th class="px-4 py-3 text-right">Credit</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="border-t"><td class="px-4 py-3">{{ $row->journalEntry->journal_date->toDateString() }}</td><td class="px-4 py-3">{{ $row->journalEntry->journal_number }}</td><td class="px-4 py-3">{{ $row->journalEntry->reference_number }}</td><td class="px-4 py-3">{{ $row->account->code }} — {{ $row->account->name }}</td><td class="px-4 py-3 text-right">{{ $row->debit }}</td><td class="px-4 py-3 text-right">{{ $row->credit }}</td></tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">No posted activity for this selection.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $rows->links() }}</div>
</x-app-layout>
