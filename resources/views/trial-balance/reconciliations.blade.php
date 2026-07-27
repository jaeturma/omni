<x-app-layout title="Subledger Reconciliation">
    <x-page-header title="Subledger Reconciliation" description="Control-account balances compared with independent operational subledgers. Differences are never adjusted automatically." />
    <nav class="mb-5 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('trial-balance.index', request()->query()) }}">Trial balance</a>
        <a href="{{ route('subledger-reconciliations.print', request()->query()) }}">Print</a>
        @can('subledger-reconciliation.export')<a href="{{ route('subledger-reconciliations.export', request()->query()) }}">CSV export</a>@endcan
    </nav>
    <x-trial-balance-filters :action="route('subledger-reconciliations.index')" :$filters :$periods :$accounts />
    <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead><tr><th class="px-4 py-3 text-left">Control account</th><th class="text-left">Type</th><th class="text-right">Ledger</th><th class="text-right">Subledger</th><th class="text-right">Difference</th><th class="text-center">Sources</th><th class="pr-4 text-left">Status / drilldown</th></tr></thead>
        <tbody>@foreach($rows as $row)<tr class="border-t"><td class="px-4 py-3">{{ $row['account']->code }} — {{ $row['account']->name }}</td><td>{{ str($row['account']->control_account_type)->headline() }}</td><td class="text-right">{{ number_format((float) $row['ledger'], 4) }}</td><td class="text-right">{{ $row['available'] ? number_format((float) $row['subledger'], 4) : '—' }}</td><td class="text-right font-semibold {{ $row['available'] && bccomp($row['difference'], '0', 4) !== 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $row['available'] ? number_format((float) $row['difference'], 4) : '—' }}</td><td class="text-center">{{ $row['source_count'] }}</td><td class="pr-4">@if(!$row['available'])Unavailable — no operational subledger exists@elseif(bccomp($row['difference'], '0', 4) === 0)<span class="text-emerald-700">Reconciled</span>@else<span class="text-red-700">Difference</span>@endif @if($row['drilldown'])· <a href="{{ $row['drilldown'] }}">View sources</a>@endif</td></tr>@endforeach</tbody></table>
    </div>
</x-app-layout>
