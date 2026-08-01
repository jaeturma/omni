<x-app-layout title="Management Financial Report Pack">
    <x-page-header title="Management Financial Report Pack" description="Generated summaries from posted accounting records." />
    <x-financial-report-metadata :metadata="$reportMetadata" :filters="$filters" />

    <div class="mt-5 flex flex-wrap gap-3">
        <a href="{{ route('dashboard', ['fiscal_period_id' => $period->id]) }}">Back to dashboard</a>
        @can('financial-report-pack.download')
            <a class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white" href="{{ route('financial-report-pack.download', $filters) }}">Download CSV pack</a>
        @endcan
    </div>

    @php
        $summaries = [
            'Income Statement' => $pack['income_statement']['summary'],
            'Balance Sheet' => $pack['balance_sheet']['summary'],
            'Cash-flow Statement' => $pack['cash_flow_statement']['summary'],
            "Owner's Equity Statement" => $pack['owner_equity_statement']['summary'],
            'Trial Balance Summary' => $pack['trial_balance_summary']['totals'],
            'AR Aging Summary' => $pack['ar_aging_summary'],
            'AP Aging Summary' => $pack['ap_aging_summary'],
            'Inventory-valuation Summary' => $pack['inventory_valuation_summary'],
        ];
        $summaries['Cash-position Summary'] = ['cash_and_cash_equivalents' => $pack['cash_position_summary']['total'], 'unreconciled' => $pack['cash_position_summary']['unreconciled']];
    @endphp
    <section class="mt-6 grid gap-5 lg:grid-cols-2">
        @foreach($summaries as $title => $summary)
            <article class="overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">{{ $title }}</h2>
                <dl class="divide-y divide-slate-100 text-sm">
                    @foreach($summary as $label => $amount)
                        @if($amount !== null && !is_array($amount))
                            <div class="flex justify-between gap-4 px-4 py-2"><dt>{{ str($label)->headline() }}</dt><dd class="font-medium">{{ is_numeric($amount) ? '₱'.number_format((float) $amount, 2) : $amount }}</dd></div>
                        @endif
                    @endforeach
                </dl>
            </article>
        @endforeach
    </section>
</x-app-layout>
