<x-app-layout title="Account Activity">
    <x-page-header title="Account Activity" description="Running balance and tagged activity for a selected account." />
    <nav class="mb-5 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('general-ledger.index', request()->query()) }}">General ledger</a>
        <a href="{{ route('account-activity.print', request()->query()) }}">Print</a>
        @can('general-ledger.export')<a href="{{ route('account-activity.export', request()->query()) }}">CSV export</a>@endcan
    </nav>
    <x-ledger-filters :action="route('account-activity.index')" :$filters :$accounts :$sourceTypes :$customers :$suppliers :$financialAccounts :$products :$warehouses require-account />
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach(['Opening' => $opening, 'Debit' => $debit, 'Credit' => $credit, 'Closing' => $closing] as $label => $value)<div class="rounded-xl bg-white p-4 ring-1 ring-slate-200"><div class="text-xs text-slate-500">{{ $label }}</div><div class="text-lg font-semibold">PHP {{ number_format((float) $value, 4) }}</div></div>@endforeach
    </div>
    @include('ledger-reports.partials.lines')
</x-app-layout>
