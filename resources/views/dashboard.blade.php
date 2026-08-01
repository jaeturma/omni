<x-app-layout title="Financial Dashboard">
    <x-page-header title="Financial Dashboard" description="Decision-focused balances and period results from posted accounting records." />

    <form method="GET" action="{{ route('financial-dashboard') }}" class="grid gap-4 rounded-xl bg-white p-5 ring-1 ring-slate-200 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            Fiscal period
            <select name="fiscal_period_id" class="rounded-lg border-slate-300">
                @foreach($periods as $availablePeriod)
                    <option value="{{ $availablePeriod->id }}" @selected($availablePeriod->id === $period->id)>{{ $availablePeriod->fiscalYear->name }} · {{ $availablePeriod->name }} ({{ $availablePeriod->starts_on->toDateString() }} to {{ $availablePeriod->ends_on->toDateString() }})</option>
                @endforeach
            </select>
        </label>
        <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Refresh</button>
    </form>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
        <p>As of {{ $filters['as_of'] }} · Period {{ $filters['start_date'] }} to {{ $filters['end_date'] }} · Status: <strong>{{ str($open_period_status)->headline() }}</strong></p>
        <p>Last refreshed {{ $generated_at->format('Y-m-d H:i:s T') }}</p>
    </div>

    @if(!$metrics_reliable)
        <section class="mt-5 rounded-xl border border-red-300 bg-red-50 p-4 text-red-900">
            <h2 class="font-semibold">Financial values temporarily unavailable</h2>
            <p class="mt-1 text-sm">Critical accounting issues may make balances misleading. Resolve the items below, then refresh.</p>
        </section>
    @elseif($warnings->isNotEmpty())
        <section class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900">
            <h2 class="font-semibold">Accounting review required</h2>
            <p class="mt-1 text-sm">Non-critical reconciliation or close items remain open.</p>
        </section>
    @endif

    @php
        $labels = [
            'cash' => 'Cash and cash equivalents', 'accounts_receivable' => 'Accounts receivable',
            'accounts_payable' => 'Accounts payable', 'inventory_value' => 'Inventory value',
            'current_month_sales' => 'Current-month sales', 'current_quarter_sales' => 'Current-quarter sales',
            'gross_profit' => 'Gross profit', 'operating_expenses' => 'Operating expenses', 'net_income' => 'Net income',
            'overdue_receivables' => 'Overdue receivables', 'overdue_payables' => 'Overdue payables',
            'unreconciled_bank_items' => 'Unreconciled bank items',
        ];
    @endphp
    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($labels as $key => $label)
            <article class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
                <p class="text-sm text-slate-600">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $metrics_reliable ? '₱'.number_format((float) $metrics[$key], 2) : 'Unavailable' }}</p>
            </article>
        @endforeach
        <article class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
            <p class="text-sm text-slate-600">Failed accounting postings</p>
            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format((int) $metrics['failed_accounting_postings']) }}</p>
        </article>
        <article class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
            <p class="text-sm text-slate-600">Open-period status</p>
            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ str($open_period_status)->headline() }}</p>
        </article>
    </section>

    @if($warnings->isNotEmpty())
        <section class="mt-6 rounded-xl bg-white p-5 ring-1 ring-slate-200">
            <h2 class="font-semibold text-slate-950">Accounting and reconciliation warnings</h2>
            <ul class="mt-3 grid gap-2 text-sm">
                @foreach($warnings as $warning)
                    <li class="flex justify-between gap-4 rounded-lg bg-slate-50 px-3 py-2"><span>{{ $warning['label'] }}</span><strong>{{ $warning['count'] }}</strong></li>
                @endforeach
            </ul>
        </section>
    @endif

    @can('financial-report-pack.generate')
        <section class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-slate-900 p-5 text-white">
            <div><h2 class="font-semibold">Management financial report pack</h2><p class="mt-1 text-sm text-slate-300">Generate all nine required summaries for this exact fiscal period.</p></div>
            <a class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900" href="{{ route('financial-report-pack.show', $filters) }}">Generate report pack</a>
        </section>
    @endcan
</x-app-layout>
