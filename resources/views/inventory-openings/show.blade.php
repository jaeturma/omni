<x-app-layout title="Inventory Opening Balance">
    <x-page-header :title="$opening->batch_number ?? 'Draft #'.$opening->id" :description="$opening->warehouse->code.' — '.$opening->warehouse->name" />
    <div class="mb-5 flex flex-wrap justify-end gap-3">
        @if($opening->status === \App\Enums\InventoryOpeningStatus::Draft)
            @can('post', $opening)<form method="POST" action="{{ route('inventory-opening-balances.transition', $opening) }}" onsubmit="return confirm('Post this opening balance? Posted records cannot be edited.')">@csrf @method('PATCH')<input type="hidden" name="status" value="posted"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Post</button></form>@endcan
        @elseif($opening->status === \App\Enums\InventoryOpeningStatus::Posted)
            @can('void', $opening)<form method="POST" action="{{ route('inventory-opening-balances.transition', $opening) }}" class="flex flex-wrap gap-2" onsubmit="return confirm('Void through reversal movements?')">@csrf @method('PATCH')<input type="hidden" name="status" value="voided"><input name="reason" required maxlength="1000" placeholder="Void reason" class="rounded-lg border-slate-300"><button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Void</button></form>@endcan
        @endif
    </div>
    <dl class="grid gap-4 rounded-2xl bg-white p-6 text-sm ring-1 ring-slate-200 md:grid-cols-4">
        <div><dt class="text-slate-500">Date</dt><dd class="font-semibold">{{ $opening->opening_date->format('M d, Y') }}</dd></div>
        <div><dt class="text-slate-500">Period</dt><dd class="font-semibold">{{ $opening->fiscalPeriod->name }}</dd></div>
        <div><dt class="text-slate-500">Status</dt><dd class="font-semibold">{{ str($opening->status->value)->headline() }}</dd></div>
        <div><dt class="text-slate-500">Reference</dt><dd class="font-semibold">{{ $opening->reference ?? '—' }}</dd></div>
    </dl>
    <div class="mt-6 overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Product</th><th>Unit</th><th class="text-right">Quantity</th><th class="text-right">Unit cost</th><th class="pr-5 text-right">Total cost</th></tr></thead>
            <tbody>@foreach($opening->lines as $line)<tr class="border-t"><td class="px-5 py-3">{{ $line->product->sku }} — {{ $line->product->name }}</td><td class="text-center">{{ $line->product->unitOfMeasure->code }}</td><td class="text-right">{{ number_format((float) $line->quantity, 4) }}</td><td class="text-right">PHP {{ number_format((float) $line->unit_cost, 4) }}</td><td class="pr-5 text-right font-semibold">PHP {{ number_format((float) $line->total_cost, 4) }}</td></tr>@endforeach</tbody>
        </table>
    </div>
    @if($opening->void_reason)<p class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-800"><strong>Void reason:</strong> {{ $opening->void_reason }}</p>@endif
</x-app-layout>
