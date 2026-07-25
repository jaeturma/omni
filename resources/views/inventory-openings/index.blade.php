<x-app-layout title="Inventory Opening Balances">
    <x-page-header title="Inventory Opening Balances" description="Controlled starting quantities and costs by warehouse." />
    <div class="mb-5 flex justify-end">
        @can('create', \App\Models\InventoryOpeningBalance::class)
            <a href="{{ route('inventory-opening-balances.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">New opening balance</a>
        @endcan
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead><tr><th class="px-5 py-3 text-left">Batch</th><th>Date</th><th>Warehouse</th><th>Period</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($openings as $opening)
                    <tr class="border-t">
                        <td class="px-5 py-3"><a class="font-semibold text-blue-700" href="{{ route('inventory-opening-balances.show', $opening) }}">{{ $opening->batch_number ?? 'Draft #'.$opening->id }}</a></td>
                        <td class="text-center">{{ $opening->opening_date->format('M d, Y') }}</td>
                        <td class="text-center">{{ $opening->warehouse->code }} — {{ $opening->warehouse->name }}</td>
                        <td class="text-center">{{ $opening->fiscalPeriod->name }}</td>
                        <td class="text-center">{{ str($opening->status->value)->headline() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-slate-500">No inventory opening balances found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $openings->links() }}</div>
</x-app-layout>
