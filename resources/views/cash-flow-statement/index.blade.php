<x-app-layout title="Cash Flow Statement">
    <x-page-header title="Cash Flow Statement" description="Indirect-method cash flows from posted journals and explicit account mappings." />

    <form method="GET" action="{{ route('cash-flow-statement.index') }}" class="grid gap-4 rounded-xl bg-white p-5 ring-1 ring-slate-200 md:grid-cols-4">
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

    <div class="mt-5 flex flex-wrap gap-3 text-sm">
        @can('financial-reports.print')<a href="{{ route('cash-flow-statement.print', $filters) }}">Print view</a>@endcan
        @can('cash-flow-statement.export')<a href="{{ route('cash-flow-statement.export', $filters) }}">Export CSV</a>@endcan
        @can('cash-flow-mapping.manage')<a href="{{ route('cash-flow-statement.mappings', $filters) }}">Review mappings</a>@endcan
    </div>

    <div class="mt-5 rounded-xl p-4 text-sm ring-1 {{ $final_ready ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-red-50 text-red-900 ring-red-200' }}">
        {{ $final_ready ? 'Reconciled and final ready.' : 'Not final ready.' }}
        @if(!$reconciled) Ending-cash reconciliation difference: PHP {{ $display_summary['reconciliation_difference'] }}. @endif
        @if($has_unclassified) Material unclassified activity must be mapped; no classification was inferred. @endif
    </div>

    <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Activity</th><th class="px-4 py-3 text-right">PHP</th></tr></thead>
            <tbody>
                @foreach($sections as $section)
                    <tr class="border-t bg-slate-50 font-semibold"><td class="px-4 py-3">{{ $section['label'] }}</td><td></td></tr>
                    @forelse($section['rows'] as $row)
                        <tr class="border-t">
                            <td class="px-4 py-3">
                                @if($row['account'] && auth()->user()->can('cash-flow-statement.drilldown'))
                                    <a class="text-blue-700" href="{{ route('cash-flow-statement.drilldown', ['account' => $row['account'], ...$filters]) }}">{{ $row['label'] }}</a>
                                @else
                                    {{ $row['label'] }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['display_amount'] }}</td>
                        </tr>
                    @empty
                        <tr class="border-t"><td class="px-4 py-3 text-slate-500">No activity</td><td class="px-4 py-3 text-right">0.00</td></tr>
                    @endforelse
                    <tr class="border-t font-semibold"><td class="px-4 py-3">Net cash from {{ str($section['label'])->lower() }}</td><td class="px-4 py-3 text-right">{{ $section['display_total'] }}</td></tr>
                @endforeach
                <tr class="border-t-2 font-semibold"><td class="px-4 py-3">Beginning cash and cash equivalents</td><td class="px-4 py-3 text-right">{{ $display_summary['beginning_cash'] }}</td></tr>
                <tr class="border-t font-semibold"><td class="px-4 py-3">Net change in cash</td><td class="px-4 py-3 text-right">{{ $display_summary['net_change'] }}</td></tr>
                <tr class="border-t font-bold"><td class="px-4 py-3">Ending cash and cash equivalents</td><td class="px-4 py-3 text-right">{{ $display_summary['ending_cash'] }}</td></tr>
                <tr class="border-t font-semibold"><td class="px-4 py-3">Balance-sheet cash</td><td class="px-4 py-3 text-right">{{ $display_summary['balance_sheet_cash'] }}</td></tr>
            </tbody>
        </table>
    </div>
</x-app-layout>
