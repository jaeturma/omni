<x-app-layout title="Comparative Financial Reports">
    <x-page-header title="Comparative Financial Reports" description="Like-for-like income statement and balance sheet trends across selected periods." />

    <form method="GET" action="{{ route('comparative-reports.index') }}" class="grid gap-4 rounded-xl bg-white p-5 ring-1 ring-slate-200 md:grid-cols-3">
        <label class="flex flex-col gap-1 text-sm font-medium">Report
            <select name="report_type" class="rounded-lg border border-slate-300 px-3 py-2">
                <option value="income_statement" @selected($filters['report_type'] === 'income_statement')>Income Statement</option>
                <option value="balance_sheet" @selected($filters['report_type'] === 'balance_sheet')>Balance Sheet</option>
            </select>
        </label>
        <label class="flex flex-col gap-1 text-sm font-medium">Comparison
            <select name="comparison_type" class="rounded-lg border border-slate-300 px-3 py-2">
                <option value="prior_month" @selected($filters['comparison_type'] === 'prior_month')>Month versus prior month</option>
                <option value="prior_quarter" @selected($filters['comparison_type'] === 'prior_quarter')>Quarter versus prior quarter</option>
                <option value="prior_year" @selected($filters['comparison_type'] === 'prior_year')>Same period last year</option>
                <option value="prior_ytd" @selected($filters['comparison_type'] === 'prior_ytd')>YTD versus prior-year YTD</option>
                <option value="custom" @selected($filters['comparison_type'] === 'custom')>Custom periods</option>
            </select>
        </label>
        <label class="flex flex-col gap-1 text-sm font-medium">Reference date
            <input type="date" name="reference_date" value="{{ $filters['reference_date'] }}" class="rounded-lg border border-slate-300 px-3 py-2">
        </label>
        <label class="flex flex-col gap-1 text-sm font-medium">Current start
            <input type="date" name="current_start_date" value="{{ $filters['current_start_date'] }}" class="rounded-lg border border-slate-300 px-3 py-2">
            @error('current_start_date')<span class="text-red-700">{{ $message }}</span>@enderror
        </label>
        <label class="flex flex-col gap-1 text-sm font-medium">Current end
            <input type="date" name="current_end_date" value="{{ $filters['current_end_date'] }}" class="rounded-lg border border-slate-300 px-3 py-2">
            @error('current_end_date')<span class="text-red-700">{{ $message }}</span>@enderror
        </label>
        <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-white">Run comparison</button></div>
        <label class="flex flex-col gap-1 text-sm font-medium">Comparison start
            <input type="date" name="comparison_start_date" value="{{ $filters['comparison_start_date'] }}" class="rounded-lg border border-slate-300 px-3 py-2">
            @error('comparison_start_date')<span class="text-red-700">{{ $message }}</span>@enderror
        </label>
        <label class="flex flex-col gap-1 text-sm font-medium">Comparison end
            <input type="date" name="comparison_end_date" value="{{ $filters['comparison_end_date'] }}" class="rounded-lg border border-slate-300 px-3 py-2">
            @error('comparison_end_date')<span class="text-red-700">{{ $message }}</span>@enderror
        </label>
    </form>

    <div class="mt-5 flex gap-3 text-sm">
        @can('financial-reports.print')<a href="{{ route('comparative-reports.print', $filters) }}">Print view</a>@endcan
        @can('comparative-reports.export')<a href="{{ route('comparative-reports.export', $filters) }}">Export CSV</a>@endcan
    </div>

    <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr><th class="px-4 py-3 text-left">{{ $report_label }}</th><th class="px-4 py-3 text-right">{{ $current_label }}</th><th class="px-4 py-3 text-right">{{ $comparison_label }}</th><th class="px-4 py-3 text-right">Variance</th><th class="px-4 py-3 text-right">Variance %</th></tr>
            </thead>
            <tbody>
                @foreach($sections as $section)
                    <tr class="border-t bg-slate-50 font-semibold"><td colspan="5" class="px-4 py-3">{{ $section['label'] }}</td></tr>
                    @foreach($section['rows'] as $row)
                        <tr class="border-t">
                            <td @class(['px-4 py-3', 'pl-9' => $row['depth'] === 1, 'pl-14' => $row['depth'] >= 2])>{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['display_current_amount'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['display_comparison_amount'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['display_absolute_variance'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['display_percentage_variance'] ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t font-semibold"><td class="px-4 py-3">Total {{ $section['label'] }}</td><td class="px-4 py-3 text-right">{{ $section['display_current_amount'] }}</td><td class="px-4 py-3 text-right">{{ $section['display_comparison_amount'] }}</td><td class="px-4 py-3 text-right">{{ $section['display_absolute_variance'] }}</td><td class="px-4 py-3 text-right">{{ $section['display_percentage_variance'] ?? 'N/A' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
