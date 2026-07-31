<x-app-layout title="Cash Flow Mapping Review">
    <x-page-header title="Cash Flow Mapping Review" description="Explicit mappings used by the indirect-method cash-flow statement." />
    <nav class="mb-5 text-sm"><a href="{{ route('cash-flow-statement.index', $filters) }}">Back to cash flow statement</a></nav>
    <div class="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Cash-flow mapping</th><th class="px-4 py-3"></th></tr></thead>
            <tbody>
                @foreach($accounts as $account)
                    <tr class="border-t"><td class="px-4 py-3">{{ $account->code }} — {{ $account->name }}</td><td class="px-4 py-3">{{ str($account->account_type->value)->headline() }}</td><td class="px-4 py-3 {{ $account->cash_flow_classification ? '' : 'font-semibold text-red-700' }}">{{ $account->cash_flow_classification ? str($account->cash_flow_classification->value)->headline() : 'Not mapped' }}</td><td class="px-4 py-3 text-right">@can('financial-report-settings.manage')<a href="{{ route('accounts.edit', $account) }}">Edit account</a>@endcan</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
