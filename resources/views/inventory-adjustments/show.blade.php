<x-app-layout title="Inventory Adjustment">
    <x-page-header :title="$adjustment->adjustment_number ?? 'Draft #'.$adjustment->id" :description="$adjustment->warehouse->code.' — '.$adjustment->warehouse->name" />
    <div class="mb-5 flex flex-wrap justify-end gap-3">
        @if($adjustment->status === \App\Enums\InventoryAdjustmentStatus::Draft)
            @can('approve', $adjustment)<form method="POST" action="{{ route('inventory-adjustments.transition', $adjustment) }}" onsubmit="return confirm('Approve this adjustment?')">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Approve</button></form>@endcan
        @elseif($adjustment->status === \App\Enums\InventoryAdjustmentStatus::Approved)
            @can('post', $adjustment)<form method="POST" action="{{ route('inventory-adjustments.transition', $adjustment) }}" onsubmit="return confirm('Post this approved adjustment?')">@csrf @method('PATCH')<input type="hidden" name="status" value="posted"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Post</button></form>@endcan
        @elseif($adjustment->status === \App\Enums\InventoryAdjustmentStatus::Posted)
            @can('void', $adjustment)<form method="POST" action="{{ route('inventory-adjustments.transition', $adjustment) }}" class="flex flex-wrap gap-2" onsubmit="return confirm('Void through reversal movements?')">@csrf @method('PATCH')<input type="hidden" name="status" value="voided"><input name="reason" required maxlength="1000" placeholder="Void reason" class="rounded-lg border-slate-300"><button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Void</button></form>@endcan
        @endif
    </div>
    <dl class="grid gap-4 rounded-2xl bg-white p-6 text-sm ring-1 ring-slate-200 md:grid-cols-4">
        <div><dt class="text-slate-500">Date</dt><dd class="font-semibold">{{ $adjustment->adjustment_date->format('M d, Y') }}</dd></div><div><dt class="text-slate-500">Type</dt><dd class="font-semibold">{{ $adjustment->type === 'in' ? 'Stock in' : 'Stock out' }}</dd></div>
        <div><dt class="text-slate-500">Reason</dt><dd class="font-semibold">{{ $adjustment->reason->name }}</dd></div><div><dt class="text-slate-500">Status</dt><dd class="font-semibold">{{ str($adjustment->status->value)->headline() }}</dd></div>
        <div class="md:col-span-4"><dt class="text-slate-500">Explanation</dt><dd class="font-semibold">{{ $adjustment->explanation }}</dd></div>
    </dl>
    <div class="mt-6 overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200"><table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Product</th><th>Unit</th><th class="text-right">Quantity</th><th class="text-right">Unit cost</th><th class="pr-5 text-right">Total cost</th></tr></thead>
        <tbody>@foreach($adjustment->lines as $line)<tr class="border-t"><td class="px-5 py-3">{{ $line->product->sku }} — {{ $line->product->name }}</td><td class="text-center">{{ $line->product->unitOfMeasure->code }}</td><td class="text-right">{{ number_format((float) $line->quantity, 4) }}</td><td class="text-right">{{ $line->unit_cost === null ? 'At posting' : 'PHP '.number_format((float) $line->unit_cost, 4) }}</td><td class="pr-5 text-right font-semibold">{{ $line->total_cost === null ? 'At posting' : 'PHP '.number_format((float) $line->total_cost, 4) }}</td></tr>@endforeach</tbody>
    </table></div>
    @if($adjustment->void_reason)<p class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-800"><strong>Void reason:</strong> {{ $adjustment->void_reason }}</p>@endif
</x-app-layout>
