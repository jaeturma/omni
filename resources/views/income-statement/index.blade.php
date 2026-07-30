<x-app-layout title="Income Statement">
    <x-page-header title="Income Statement" description="Period activity from posted journals, presented using the configured chart-of-accounts hierarchy." />

    <nav class="mb-5 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('income-statement.print', request()->query()) }}">Print</a>
        @can('income-statement.export')
            <a href="{{ route('income-statement.export', request()->query()) }}">CSV export</a>
        @endcan
    </nav>

    <form method="GET" action="{{ route('income-statement.index') }}" class="grid gap-4 rounded-xl bg-white p-4 ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-4">
        <label class="grid gap-1 text-sm">Fiscal period
            <select name="fiscal_period_id" class="rounded-lg border-slate-300">
                <option value="">Custom range</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}" @selected(($filters['fiscal_period_id'] ?? null) == $period->id)>{{ $period->fiscalYear->name }} — {{ $period->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm">View
            <select name="report_view" class="rounded-lg border-slate-300">
                <option value="period" @selected($filters['report_view'] === 'period')>Current period</option>
                <option value="year_to_date" @selected($filters['report_view'] === 'year_to_date')>Year to date</option>
            </select>
        </label>
        <label class="grid gap-1 text-sm">Start date
            <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="rounded-lg border-slate-300">
            @error('start_date')<span class="text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="grid gap-1 text-sm">End date
            <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="rounded-lg border-slate-300">
            @error('end_date')<span class="text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="show_zero_balances" value="0">
            <input type="checkbox" name="show_zero_balances" value="1" @checked($filters['show_zero_balances'])>
            Show zero-balance accounts
        </label>
        <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Apply filters</button></div>
    </form>

    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm ring-1 ring-slate-200">
        <span class="font-semibold">{{ $filters['report_view'] === 'year_to_date' ? 'Year to date' : 'Current period' }}</span>
        · {{ $filters['start_date'] }} to {{ $filters['end_date'] }}
        · As of {{ $filters['as_of'] }}
        · Fiscal period {{ $filters['fiscal_period_id'] ?? 'Custom' }}
        · {{ $filters['show_zero_balances'] ? 'Zero balances shown' : 'Zero balances hidden' }}
    </div>

    <div class="mt-6 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-right">PHP</th></tr></thead>
            <tbody>
                @foreach($sections as $section)
                    @continue($section['key'] === 'income_tax' && ! $has_income_tax)
                    <tr class="border-t bg-slate-50 font-semibold"><td class="px-4 py-3">{{ $section['label'] }}</td><td></td></tr>
                    @forelse($section['rows'] as $row)
                        <tr class="border-t {{ $row['account']->is_header ? 'font-semibold' : '' }}">
                            <td class="px-4 py-3">
                                {{ str_repeat('— ', $row['depth']) }}
                                @can('income-statement.drilldown')
                                    <a class="text-blue-700" href="{{ route('income-statement.drilldown', ['account' => $row['account'], ...$filters]) }}">{{ $row['account']->code }} — {{ $row['account']->name }}</a>
                                @else
                                    {{ $row['account']->code }} — {{ $row['account']->name }}
                                @endcan
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['display_amount'] }}</td>
                        </tr>
                    @empty
                        <tr class="border-t"><td class="px-4 py-3 text-slate-500">No activity</td><td class="px-4 py-3 text-right">0.00</td></tr>
                    @endforelse
                    <tr class="border-t font-semibold"><td class="px-4 py-3">Total {{ $section['label'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $section['display_total'] }}</td></tr>

                    @if($section['key'] === 'contra_revenue')
                        <tr class="border-t-2 font-bold"><td class="px-4 py-3">Net Sales</td><td class="px-4 py-3 text-right">{{ $display_summary['net_sales'] }}</td></tr>
                    @elseif($section['key'] === 'cost_of_sales')
                        <tr class="border-t-2 font-bold"><td class="px-4 py-3">Gross Profit</td><td class="px-4 py-3 text-right">{{ $display_summary['gross_profit'] }}</td></tr>
                    @elseif($section['key'] === 'operating_expenses')
                        <tr class="border-t-2 font-bold"><td class="px-4 py-3">Operating Income</td><td class="px-4 py-3 text-right">{{ $display_summary['operating_income'] }}</td></tr>
                    @elseif($section['key'] === 'other_expenses')
                        <tr class="border-t-2 font-bold"><td class="px-4 py-3">Net Income Before Tax</td><td class="px-4 py-3 text-right">{{ $display_summary['net_income_before_tax'] }}</td></tr>
                    @elseif($section['key'] === 'income_tax')
                        <tr class="border-t-2 font-bold"><td class="px-4 py-3">Net Income After Tax</td><td class="px-4 py-3 text-right">{{ $display_summary['net_income_after_tax'] }}</td></tr>
                    @endif
                @endforeach
                @unless($has_income_tax)
                    <tr class="border-t-2 font-bold"><td class="px-4 py-3">Net Income</td><td class="px-4 py-3 text-right">{{ $display_summary['net_income_after_tax'] }}</td></tr>
                @endunless
            </tbody>
        </table>
    </div>
</x-app-layout>
