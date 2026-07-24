<x-app-layout title="Fund Transfers">
    <x-page-header title="Fund Transfers" description="Atomic transfers between operational financial accounts." />
    <div class="mb-5 flex justify-end">
        @can('create', \App\Models\FundTransfer::class)
            <a href="{{ route('fund-transfers.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Record transfer</a>
        @endcan
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead><tr><th class="px-5 py-3 text-left">Number</th><th>Date</th><th>Source</th><th>Destination</th><th class="text-right">Amount</th><th class="text-right">Fee</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($transfers as $transfer)
                    <tr class="border-t"><td class="px-5 py-3"><a href="{{ route('fund-transfers.show', $transfer) }}" class="font-semibold text-blue-700">{{ $transfer->transfer_number ?? 'Draft #'.$transfer->id }}</a></td><td class="text-center">{{ $transfer->transfer_date->format('M d, Y') }}</td><td class="text-center">{{ $transfer->sourceFinancialAccount->code }}</td><td class="text-center">{{ $transfer->destinationFinancialAccount->code }}</td><td class="text-right">PHP {{ number_format((float) $transfer->amount, 2) }}</td><td class="text-right">PHP {{ number_format((float) $transfer->transfer_fee, 2) }}</td><td class="text-center capitalize">{{ str($transfer->status->value)->headline() }}</td></tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center">No fund transfers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $transfers->links() }}</div>
</x-app-layout>
