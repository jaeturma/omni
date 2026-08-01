<x-app-layout title="Management Reports">
    <x-page-header title="Management Profitability Reports" description="Sales, margin, expense, collection, turnover, and monthly profitability analysis." />

    <form method="GET" action="{{ route('management-reports.index') }}" class="grid gap-4 rounded-xl bg-white p-5 ring-1 ring-slate-200 md:grid-cols-3">
        <label class="flex flex-col gap-1 text-sm font-medium">Report
            <select name="report" class="rounded-lg border border-slate-300 px-3 py-2">
                @foreach(['sales' => 'Sales Analysis', 'profitability' => 'Gross Profit and Margin', 'expenses' => 'Expense Analysis', 'collections' => 'Collection Performance', 'turnover' => 'Turnover Indicators', 'trend' => 'Monthly Profitability Trend'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['report'] === $value)>{{ $label }}</option>
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
        <label class="flex flex-col gap-1 text-sm font-medium">Customer
            <select name="customer_id" class="rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All customers</option>
                @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) $filters['customer_id'] === (string) $customer->id)>{{ $customer->name }}</option>@endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1 text-sm font-medium">Product / service category
            <select name="category_id" class="rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All categories</option>
                @foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>@endforeach
            </select>
        </label>
        <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-white">Run report</button></div>
    </form>

    <div class="mt-5 flex gap-3 text-sm">
        @can('financial-reports.print')<a href="{{ route('management-reports.print', $filters) }}">Print view</a>@endcan
        @can('management-reports.export')<a href="{{ route('management-reports.export', $filters) }}">Export CSV</a>@endcan
    </div>

    <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950">
        <span class="font-semibold">Data source:</span> {{ $source_note }}
    </div>

    <div class="mt-6 grid gap-6">
        @foreach($sections as $section)
            <section class="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
                <h2 class="border-b px-4 py-3 font-semibold">{{ $section['label'] }}</h2>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50"><tr>@foreach($section['columns'] as $column)<th class="px-4 py-3 text-left">{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($section['rows'] as $row)
                            <tr class="border-t">@foreach($row as $value)<td class="px-4 py-3 tabular-nums">{{ $value }}</td>@endforeach</tr>
                        @empty
                            <tr class="border-t"><td colspan="{{ count($section['columns']) }}" class="px-4 py-8 text-center text-slate-500">No data for the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>
</x-app-layout>
