<x-app-layout title="Balance Sheet">
    <x-page-header title="Balance Sheet" description="Cumulative asset, liability, and owner's-equity balances from posted journals." />

    <nav class="mb-5 flex flex-wrap gap-3 text-sm">
        @can('financial-reports.print')<a href="{{ route('balance-sheet.print', request()->query()) }}">Print</a>@endcan
        @can('balance-sheet.export')
            <a href="{{ route('balance-sheet.export', request()->query()) }}">CSV export</a>
        @endcan
    </nav>

    <form method="GET" action="{{ route('balance-sheet.index') }}" class="grid gap-4 rounded-xl bg-white p-4 ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-4">
        <label class="grid gap-1 text-sm">Fiscal period
            <select name="fiscal_period_id" class="rounded-lg border-slate-300">
                <option value="">Custom as-of date</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}" @selected(($filters['fiscal_period_id'] ?? null) == $period->id)>{{ $period->fiscalYear->name }} — {{ $period->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm">As of
            <input type="date" name="as_of" value="{{ $filters['as_of'] }}" class="rounded-lg border-slate-300">
            @error('as_of')<span class="text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="show_zero_balances" value="0">
            <input type="checkbox" name="show_zero_balances" value="1" @checked($filters['show_zero_balances'])>
            Show zero-balance accounts
        </label>
        <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Apply filters</button></div>
    </form>

    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm ring-1 ring-slate-200">
        As of {{ $filters['as_of'] }}
        · Fiscal-year start {{ $filters['fiscal_year_start'] }}
        · Fiscal period {{ $filters['fiscal_period_id'] ?? 'Custom' }}
        · {{ $filters['show_zero_balances'] ? 'Zero balances shown' : 'Zero balances hidden' }}
        · Current-year earnings {{ $current_year_earnings_derived ? 'derived from income-statement activity' : 'from posted closing entries' }}
    </div>

    <div class="mt-5 flex items-center justify-between gap-4 rounded-xl p-4 ring-1 {{ $balanced ? 'bg-emerald-50 ring-emerald-200' : 'bg-red-50 ring-red-200' }}">
        <span class="font-semibold">{{ $balanced ? 'Balanced — final ready' : 'Out of balance — not final ready' }}</span>
        <span class="text-sm">Difference PHP {{ $display_summary['difference'] }}</span>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-right">PHP</th></tr></thead>
            <tbody>
                @foreach($sections as $section)
                    <tr class="border-t bg-slate-50 font-semibold"><td class="px-4 py-3">{{ $section['label'] }}</td><td></td></tr>
                    @forelse($section['rows'] as $row)
                        <tr class="border-t {{ $row['account']->is_header ? 'font-semibold' : '' }}">
                            <td class="px-4 py-3">
                                {{ str_repeat('— ', $row['depth']) }}
                                @can('balance-sheet.drilldown')
                                    <a class="text-blue-700" href="{{ route('balance-sheet.drilldown', ['account' => $row['account'], ...$filters]) }}">{{ $row['account']->code }} — {{ $row['account']->name }}</a>
                                @else
                                    {{ $row['account']->code }} — {{ $row['account']->name }}
                                @endcan
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['display_amount'] }}</td>
                        </tr>
                    @empty
                        <tr class="border-t"><td class="px-4 py-3 text-slate-500">No balance</td><td class="px-4 py-3 text-right">0.00</td></tr>
                    @endforelse
                    <tr class="border-t font-semibold"><td class="px-4 py-3">Total {{ $section['label'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $section['display_total'] }}</td></tr>

                    @if($section['key'] === 'non_current_assets')
                        <tr class="border-t-2 font-bold"><td class="px-4 py-3">Total Assets</td><td class="px-4 py-3 text-right">{{ $display_summary['total_assets'] }}</td></tr>
                    @elseif($section['key'] === 'non_current_liabilities')
                        <tr class="border-t-2 font-bold"><td class="px-4 py-3">Total Liabilities</td><td class="px-4 py-3 text-right">{{ $display_summary['total_liabilities'] }}</td></tr>
                    @elseif($section['key'] === 'current_year_earnings')
                        <tr class="border-t-2 font-bold"><td class="px-4 py-3">Total Owner's Equity</td><td class="px-4 py-3 text-right">{{ $display_summary['total_equity'] }}</td></tr>
                        <tr class="border-t-2 font-bold"><td class="px-4 py-3">Total Liabilities and Owner's Equity</td><td class="px-4 py-3 text-right">{{ $display_summary['liabilities_and_equity'] }}</td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
