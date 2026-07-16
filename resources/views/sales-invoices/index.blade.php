<x-app-layout title="Sales Invoices">
    <x-page-header title="Sales Invoices" description="Operational customer receivables without ledger posting." />
    <div class="mb-5 flex justify-end">@can('create', \App\Models\SalesInvoice::class)<a href="{{ route('sales-invoices.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Create invoice</a>@endcan</div>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200"><table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Number</th><th>Customer</th><th>Date</th><th>Receivable</th><th>Balance</th><th>Status</th></tr></thead><tbody>
    @forelse ($invoices as $invoice)
        <tr class="border-t"><td class="px-5 py-3"><a class="font-semibold text-blue-700" href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_number ?? 'Draft #'.$invoice->id }}</a></td><td class="text-center">{{ $invoice->customer_name }}</td><td class="text-center">{{ $invoice->invoice_date->format('M d, Y') }}</td><td class="text-right">₱{{ number_format((float) $invoice->total_receivable, 2) }}</td><td class="text-right">₱{{ number_format((float) $invoice->balance_due, 2) }}</td><td class="text-center capitalize">{{ str_replace('_', ' ', $invoice->status->value) }}</td></tr>
    @empty <tr><td colspan="6" class="p-8 text-center">No invoices found.</td></tr> @endforelse
    </tbody></table></div><div class="mt-5">{{ $invoices->links() }}</div>
</x-app-layout>
