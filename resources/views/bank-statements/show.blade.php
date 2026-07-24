<x-app-layout title="Bank Statement">
    <x-page-header title="{{ $import->source_filename }}" description="{{ $import->financialAccount->code }} · {{ $import->statement_start_date->format('M d, Y') }} to {{ $import->statement_end_date->format('M d, Y') }}" />
    <div class="mb-5 flex flex-wrap justify-end gap-3">
        @can('export', $import)<a href="{{ route('bank-statements.export', $import) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Export CSV</a>@endcan
        @can('rollback', $import)
            <form method="POST" action="{{ route('bank-statements.destroy', $import) }}" onsubmit="return confirm('Roll back this import and remove all staged lines?')">
                @csrf @method('DELETE')
                <button class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700">Roll back import</button>
            </form>
        @endcan
    </div>
    @if($import->rolled_back_at)
        <div class="rounded-xl bg-amber-50 p-4 text-amber-900">This import was rolled back. Its staged lines were removed.</div>
    @else
        <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
            <table class="min-w-full text-sm">
                <thead><tr><th class="px-4 py-3">Line</th><th>Date</th><th>Posted</th><th class="text-left">Description</th><th>Reference</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($lines as $line)
                        <tr class="border-t"><td class="px-4 py-3 text-center">{{ $line->line_number }}</td><td>{{ $line->transaction_date->format('M d, Y') }}</td><td>{{ $line->posting_date->format('M d, Y') }}</td><td>{{ $line->description }}</td><td class="text-center">{{ $line->reference_number ?? '—' }}</td><td class="text-right">{{ number_format((float) $line->debit, 2) }}</td><td class="text-right">{{ number_format((float) $line->credit, 2) }}</td><td class="text-right">{{ $line->running_balance === null ? '—' : number_format((float) $line->running_balance, 2) }}</td><td class="text-center capitalize">{{ str($line->match_status->value)->headline() }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $lines->links() }}</div>
    @endif
</x-app-layout>
