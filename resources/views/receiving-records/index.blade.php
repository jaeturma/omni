<x-app-layout title="Receiving records">
    <x-page-header title="Receiving records" description="Track partial and complete supplier deliveries against purchase orders." />

    @can('create', \App\Models\ReceivingRecord::class)
        @if ($receivableOrders->isNotEmpty())
            <div class="mb-5 flex flex-wrap gap-2">
                @foreach ($receivableOrders as $order)
                    <a href="{{ route('receiving-records.create', $order) }}" class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">
                        Receive {{ $order->purchase_order_number }}
                    </a>
                @endforeach
            </div>
        @endif
    @endcan

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 text-sm font-medium">
            Search
            <input name="search" value="{{ request('search') }}" class="rounded-lg border border-slate-300 px-3 py-2">
        </label>
        <label class="flex flex-col gap-1 text-sm font-medium">
            Status
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All</option>
                @foreach (\App\Enums\ReceivingStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ ucfirst(str_replace('_', ' ', $status->value)) }}
                    </option>
                @endforeach
            </select>
        </label>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-6 py-3">Number</th>
                    <th class="px-6 py-3">Date</th>
                    <th class="px-6 py-3">Purchase order</th>
                    <th class="px-6 py-3">Supplier</th>
                    <th class="px-6 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receivingRecords as $record)
                    <tr>
                        <td class="px-6 py-4">
                            <a href="{{ route('receiving-records.show', $record) }}" class="font-semibold text-blue-700">
                                {{ $record->receiving_number ?? 'Draft #'.$record->id }}
                            </a>
                        </td>
                        <td class="px-6 py-4">{{ $record->receiving_date->format('M d, Y') }}</td>
                        <td class="px-6 py-4">{{ $record->purchaseOrder->purchase_order_number }}</td>
                        <td class="px-6 py-4">{{ $record->supplier_name }}</td>
                        <td class="px-6 py-4 capitalize">{{ str_replace('_', ' ', $record->status->value) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">No receiving records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $receivingRecords->links() }}</div>
</x-app-layout>
