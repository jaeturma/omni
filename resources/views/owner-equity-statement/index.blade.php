<x-app-layout title="Statement of Changes in Owner's Equity">
    <x-page-header title="Statement of Changes in Owner's Equity" description="Posted capital, earnings, drawings, and retained-equity adjustments for the selected period." />

    <form method="GET" action="{{ route('owner-equity-statement.index') }}" class="grid gap-4 rounded-xl bg-white p-5 ring-1 ring-slate-200 md:grid-cols-4">
        <label class="flex flex-col gap-1 text-sm font-medium">Fiscal period
            <select name="fiscal_period_id" class="rounded-lg border border-slate-300 px-3 py-2">
                <option value="">Custom range</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}" @selected((string) ($filters['fiscal_period_id'] ?? '') === (string) $period->id)>{{ $period->fiscalYear->name }} — {{ $period->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1 text-sm font-medium">Start date
            <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="rounded-lg border border-slate-300 px-3 py-2">
            @error('start_date')<span class="text-red-700">{{ $message }}</span>@enderror
        </label>
        <label class="flex flex-col gap-1 text-sm font-medium">End date
            <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="rounded-lg border border-slate-300 px-3 py-2">
            @error('end_date')<span class="text-red-700">{{ $message }}</span>@enderror
        </label>
        <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-white">Run report</button></div>
    </form>

    <div class="mt-5 flex gap-3 text-sm">
        @can('financial-reports.print')<a href="{{ route('owner-equity-statement.print', $filters) }}">Print view</a>@endcan
        @can('owner-equity-statement.export')<a href="{{ route('owner-equity-statement.export', $filters) }}">Export CSV</a>@endcan
    </div>

    <div class="mt-5 rounded-xl p-4 text-sm ring-1 {{ $final_ready ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-red-50 text-red-900 ring-red-200' }}">
        {{ $final_ready ? "Reconciled to the balance sheet and final ready." : "Closing equity does not reconcile to the balance sheet." }}
        Difference: PHP {{ $display_summary['reconciliation_difference'] }}.
    </div>

    <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Equity Activity</th><th class="px-4 py-3 text-right">PHP</th></tr></thead>
            <tbody>
                @foreach($rows as $row)
                    <tr class="border-t {{ $row['key'] === 'closing_equity' ? 'font-bold' : '' }}">
                        <td class="px-4 py-3">
                            @if($row['drilldown'] && auth()->user()->can('owner-equity-statement.drilldown'))
                                <a class="text-blue-700" href="{{ route('owner-equity-statement.drilldown', ['activity' => $row['key'], ...$filters]) }}">{{ $row['label'] }}</a>
                            @else
                                {{ $row['label'] }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['display_amount'] }}</td>
                    </tr>
                @endforeach
                <tr class="border-t font-semibold"><td class="px-4 py-3">Balance-sheet Closing Equity</td><td class="px-4 py-3 text-right">{{ $display_summary['balance_sheet_closing_equity'] }}</td></tr>
            </tbody>
        </table>
    </div>
</x-app-layout>
