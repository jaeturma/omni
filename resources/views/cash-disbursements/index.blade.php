<x-app-layout title="Cash Disbursements">
    <x-page-header title="Cash Disbursements" description="Posted cash, bank, and e-wallet outflows." />
    <div class="mb-5 flex justify-end">
        @can('create', \App\Models\CashDisbursement::class)
            <a href="{{ route('cash-disbursements.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Record disbursement</a>
        @endcan
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead><tr><th class="px-5 py-3 text-left">Number</th><th>Date</th><th>Source</th><th>Account</th><th>Payee</th><th class="text-right">Net cash out</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($disbursements as $disbursement)
                    <tr class="border-t"><td class="px-5 py-3"><a href="{{ route('cash-disbursements.show', $disbursement) }}" class="font-semibold text-blue-700">{{ $disbursement->disbursement_number ?? 'Draft #'.$disbursement->id }}</a></td><td class="text-center">{{ $disbursement->disbursement_date->format('M d, Y') }}</td><td class="text-center">{{ str($disbursement->source_type->value)->headline() }}</td><td class="text-center">{{ $disbursement->financialAccount->code }}</td><td class="text-center">{{ $disbursement->payee }}</td><td class="text-right">PHP {{ number_format((float) $disbursement->net_cash_out, 2) }}</td><td class="text-center capitalize">{{ $disbursement->status->value }}</td></tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center">No cash disbursements found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $disbursements->links() }}</div>
</x-app-layout>
