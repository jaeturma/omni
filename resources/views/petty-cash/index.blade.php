<x-app-layout title="Petty Cash">
    <x-page-header title="Petty Cash" description="Imprest funds, accountable vouchers, liquidations, and replenishments." />
    <div class="mb-5 flex flex-wrap justify-end gap-3">
        @can('create', \App\Models\PettyCashFund::class)<a href="{{ route('petty-cash.funds.create') }}" class="rounded-lg border border-blue-700 px-4 py-2 text-sm font-semibold text-blue-700">Create fund</a>@endcan
        @can('create', \App\Models\PettyCashVoucher::class)<a href="{{ route('petty-cash.vouchers.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Create voucher</a>@endcan
    </div>
    <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($funds as $fund)
            <a href="{{ route('petty-cash.funds.show', $fund) }}" class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                <div class="flex items-start justify-between gap-4"><div><p class="font-semibold">{{ $fund->financialAccount->code }} — {{ $fund->financialAccount->name }}</p><p class="text-sm text-slate-500">Custodian: {{ $fund->custodian->name }}</p></div><span class="text-xs font-semibold uppercase {{ $fund->active ? 'text-green-700' : 'text-slate-500' }}">{{ $fund->active ? 'Active' : 'Inactive' }}</span></div>
                <dl class="mt-4 grid grid-cols-2 gap-2 text-sm"><dt>Available</dt><dd class="text-right font-semibold">PHP {{ number_format((float) $fund->current_operational_balance, 2) }}</dd><dt>Approved limit</dt><dd class="text-right">PHP {{ number_format((float) $fund->approved_fund_limit, 2) }}</dd></dl>
            </a>
        @empty
            <p class="rounded-2xl bg-white p-6 text-sm ring-1 ring-slate-200">No petty-cash funds configured.</p>
        @endforelse
    </section>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Voucher</th><th>Date</th><th>Fund</th><th>Payee</th><th class="text-right">Released</th><th class="text-right">Liquidated</th><th>Status</th></tr></thead>
            <tbody>@forelse($vouchers as $voucher)<tr class="border-t"><td class="px-5 py-3"><a href="{{ route('petty-cash.vouchers.show', $voucher) }}" class="font-semibold text-blue-700">{{ $voucher->voucher_number ?? 'Draft #'.$voucher->id }}</a></td><td class="text-center">{{ $voucher->voucher_date->format('M d, Y') }}</td><td class="text-center">{{ $voucher->fund->financialAccount->code }}</td><td class="text-center">{{ $voucher->payee }}</td><td class="text-right">PHP {{ number_format((float) $voucher->amount_released, 2) }}</td><td class="text-right">PHP {{ number_format((float) $voucher->amount_liquidated, 2) }}</td><td class="text-center">{{ str($voucher->status->value)->headline() }}</td></tr>@empty<tr><td colspan="7" class="p-8 text-center">No petty-cash vouchers found.</td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="mt-5">{{ $vouchers->links() }}</div>
</x-app-layout>
