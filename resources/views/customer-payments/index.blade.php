<x-app-layout title="Customer Payments">
    <x-page-header title="Customer Payments" description="Customer settlements, allocations, and unapplied balances." />
    <div class="mb-5 flex justify-end">@can('create', \App\Models\CustomerPayment::class)<a href="{{ route('customer-payments.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Record payment</a>@endcan</div>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200"><table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Number</th><th>Customer</th><th>Date</th><th>Method</th><th class="text-right">Settlement</th><th class="text-right">Unapplied</th><th>Status</th></tr></thead><tbody>
    @forelse ($payments as $payment)
        <tr class="border-t"><td class="px-5 py-3"><a class="font-semibold text-blue-700" href="{{ route('customer-payments.show', $payment) }}">{{ $payment->payment_number ?? 'Draft #'.$payment->id }}</a></td><td class="text-center">{{ $payment->customer->name }}</td><td class="text-center">{{ $payment->payment_date->format('M d, Y') }}</td><td class="text-center">{{ $payment->paymentMethod->name }}</td><td class="text-right">₱{{ number_format((float) $payment->gross_settlement_amount, 2) }}</td><td class="text-right">₱{{ number_format((float) $payment->unapplied_amount, 2) }}</td><td class="text-center capitalize">{{ str_replace('_', ' ', $payment->status->value) }}</td></tr>
    @empty <tr><td colspan="7" class="p-8 text-center">No customer payments found.</td></tr> @endforelse
    </tbody></table></div><div class="mt-5">{{ $payments->links() }}</div>
</x-app-layout>
