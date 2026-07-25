<x-app-layout title="Inventory Adjustments">
    <x-page-header title="Inventory Adjustments" description="Controlled stock gains and losses by warehouse." />
    <div class="mb-5 flex justify-end">@can('create', \App\Models\InventoryAdjustment::class)<a href="{{ route('inventory-adjustments.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">New adjustment</a>@endcan</div>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Adjustment</th><th>Date</th><th>Warehouse</th><th>Type</th><th>Reason</th><th>Status</th></tr></thead>
            <tbody>@forelse($adjustments as $adjustment)<tr class="border-t">
                <td class="px-5 py-3"><a class="font-semibold text-blue-700" href="{{ route('inventory-adjustments.show', $adjustment) }}">{{ $adjustment->adjustment_number ?? 'Draft #'.$adjustment->id }}</a></td>
                <td class="text-center">{{ $adjustment->adjustment_date->format('M d, Y') }}</td><td class="text-center">{{ $adjustment->warehouse->code }}</td>
                <td class="text-center">{{ $adjustment->type === 'in' ? 'Stock in' : 'Stock out' }}</td><td class="text-center">{{ $adjustment->reason->name }}</td><td class="text-center">{{ str($adjustment->status->value)->headline() }}</td>
            </tr>@empty<tr><td colspan="6" class="p-8 text-center text-slate-500">No inventory adjustments found.</td></tr>@endforelse</tbody>
        </table>
    </div><div class="mt-5">{{ $adjustments->links() }}</div>
</x-app-layout>
