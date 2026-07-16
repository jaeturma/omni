<x-app-layout title="Deliveries">
    <x-page-header title="Delivery Records" description="Record order fulfillment without inventory posting." />
    <div class="mb-5 flex justify-end">@can('create', \App\Models\Delivery::class)<a href="{{ route('deliveries.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Create delivery</a>@endcan</div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"><table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Number</th><th>Order</th><th>Customer</th><th>Date</th><th>Status</th></tr></thead><tbody>
    @forelse ($deliveries as $delivery)
        <tr><td class="px-5 py-3"><a class="font-semibold text-blue-700" href="{{ route('deliveries.show', $delivery) }}">{{ $delivery->delivery_number ?? 'Draft #'.$delivery->id }}</a></td><td class="text-center">{{ $delivery->salesOrder->sales_order_number }}</td><td class="text-center">{{ $delivery->customer_name }}</td><td class="text-center">{{ $delivery->delivery_date->format('M d, Y') }}</td><td class="text-center capitalize">{{ $delivery->status->value }}</td></tr>
    @empty
        <tr><td colspan="5" class="p-8 text-center">No deliveries found.</td></tr>
    @endforelse
    </tbody></table></div><div class="mt-5">{{ $deliveries->links() }}</div>
</x-app-layout>
