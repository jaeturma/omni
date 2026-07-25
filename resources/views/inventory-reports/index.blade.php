<x-app-layout title="Inventory Reports">
    <x-page-header title="Stock Ledger, Valuation, and Alerts" description="Read-only inventory position and movement reporting from posted stock movements." />

    <nav class="mb-5 flex flex-wrap gap-4 text-sm">
        <a class="font-semibold text-blue-700" href="{{ route('inventory-reports.print', request()->query()) }}">Print</a>
        @can('inventory-reports.export')
            <a class="font-semibold text-blue-700" href="{{ route('inventory-reports.export', request()->query()) }}">CSV export</a>
        @endcan
    </nav>

    <form method="GET" action="{{ route('inventory-reports.index') }}" class="mb-6 grid gap-4 rounded-xl bg-white p-4 ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-4">
        <label class="grid gap-1 text-sm">Start date<input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="rounded-lg border-slate-300">@error('start_date')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="grid gap-1 text-sm">End date<input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="rounded-lg border-slate-300">@error('end_date')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="grid gap-1 text-sm">As of<input type="date" name="as_of" value="{{ $filters['as_of'] }}" class="rounded-lg border-slate-300">@error('as_of')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="grid gap-1 text-sm">Product<select name="product_service_id" class="rounded-lg border-slate-300"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected($filters['product_service_id'] == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-sm">Category<select name="category_id" class="rounded-lg border-slate-300"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($filters['category_id'] == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-sm">Brand<select name="brand_id" class="rounded-lg border-slate-300"><option value="">All brands</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected($filters['brand_id'] == $brand->id)>{{ $brand->name }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-sm">Warehouse<select name="warehouse_id" class="rounded-lg border-slate-300"><option value="">All warehouses</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($filters['warehouse_id'] == $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-sm">Movement type<select name="movement_type" class="rounded-lg border-slate-300"><option value="">All movement types</option>@foreach($movementTypes as $type)<option value="{{ $type->value }}" @selected($filters['movement_type'] === $type->value)>{{ str($type->value)->headline() }}</option>@endforeach</select></label>
        <button class="w-fit rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Apply filters</button>
    </form>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach(['As-of quantity' => $summary['as_of_quantity'], 'Opening quantity' => $summary['opening_quantity'], 'Range movement' => $summary['movement_quantity'], 'Closing quantity' => $summary['closing_quantity']] as $label => $value)
            <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200"><p class="text-xs text-slate-500">{{ $label }}</p><p class="text-lg font-semibold">{{ number_format((float) $value, 4) }}</p></div>
        @endforeach
        @if($canViewValuation)
            @foreach(['As-of value' => $summary['as_of_value'], 'Opening value' => $summary['opening_value'], 'Range value' => $summary['movement_value'], 'Closing value' => $summary['closing_value']] as $label => $value)
                <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200"><p class="text-xs text-slate-500">{{ $label }}</p><p class="text-lg font-semibold">PHP {{ number_format((float) $value, 2) }}</p></div>
            @endforeach
        @endif
    </section>

    <h2 class="mt-7 text-lg font-semibold">Stock on hand by warehouse</h2>
    <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead><tr class="text-left"><th class="px-4 py-3">Product</th><th>Category / Brand</th><th>Warehouse</th><th class="text-right">Quantity</th>@if($canViewCost)<th class="text-right">Average cost</th>@endif @if($canViewValuation)<th class="pr-4 text-right">Value</th>@endif</tr></thead>
            <tbody>@forelse($stocks as $stock)<tr class="border-t border-slate-200"><td class="px-4 py-3">{{ $stock->product->sku }} — {{ $stock->product->name }}</td><td>{{ $stock->product->category->name }} / {{ $stock->product->brand?->name ?? 'Unbranded' }}</td><td>{{ $stock->warehouse->code }}</td><td class="text-right">{{ number_format((float) $stock->quantity, 4) }}</td>@if($canViewCost)<td class="text-right">{{ number_format((float) $stock->as_of_average_cost, 4) }}</td>@endif @if($canViewValuation)<td class="pr-4 text-right">{{ number_format((float) $stock->as_of_value, 2) }}</td>@endif</tr>@empty<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No stock matches these filters.</td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="mt-4">{{ $stocks->links() }}</div>

    <div class="mt-7 grid gap-6 lg:grid-cols-2">
        <section><h2 class="text-lg font-semibold">Low-stock and reorder alerts</h2><div class="mt-3 rounded-xl bg-white p-4 ring-1 ring-slate-200"><ul class="grid gap-2">@forelse($alerts as $product)<li class="flex justify-between gap-4"><span>{{ $product->sku }} — {{ $product->name }}</span><span class="font-semibold text-amber-700">{{ $product->report_quantity }} / reorder {{ $product->reorder_level }}</span></li>@empty<li class="text-slate-500">No reorder alerts.</li>@endforelse</ul></div></section>
        <section><h2 class="text-lg font-semibold">Negative-stock exceptions</h2><div class="mt-3 rounded-xl bg-white p-4 ring-1 ring-slate-200"><ul class="grid gap-2">@forelse($negativeStocks as $stock)<li>{{ $stock->product->sku }} at {{ $stock->warehouse->code }}: <span class="font-semibold text-red-700">{{ $stock->quantity }}</span></li>@empty<li class="text-slate-500">No negative-stock exceptions.</li>@endforelse</ul></div></section>
        <section><h2 class="text-lg font-semibold">Inventory in transit</h2><div class="mt-3 rounded-xl bg-white p-4 ring-1 ring-slate-200"><ul class="grid gap-2">@forelse($inTransit as $line)<li>{{ $line->product->sku }}: {{ $line->quantity }} from {{ $line->transfer->sourceWarehouse->code }} to {{ $line->transfer->destinationWarehouse->code }}</li>@empty<li class="text-slate-500">No inventory is in transit.</li>@endforelse</ul></div></section>
        <section><h2 class="text-lg font-semibold">Slow-moving and no-movement items</h2><div class="mt-3 rounded-xl bg-white p-4 ring-1 ring-slate-200"><ul class="grid gap-2">@forelse($slowMoving as $product)<li>{{ $product->sku }} — {{ $product->name }} <span class="text-slate-500">({{ $product->last_movement_date?->format('M d, Y') ?? 'no movement' }})</span></li>@empty<li class="text-slate-500">No slow-moving items.</li>@endforelse</ul></div></section>
    </div>

    <h2 class="mt-7 text-lg font-semibold">Stock ledger and source traceability</h2>
    <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200"><table class="min-w-full text-sm"><thead><tr class="text-left"><th class="px-4 py-3">Date</th><th>Product</th><th>Warehouse</th><th>Movement</th><th>Source</th><th class="text-right">Quantity</th>@if($canViewCost)<th class="text-right">Unit cost</th>@endif @if($canViewValuation)<th class="pr-4 text-right">Value</th>@endif</tr></thead><tbody>@forelse($ledger as $movement)<tr class="border-t border-slate-200"><td class="px-4 py-3">{{ $movement->movement_date->format('M d, Y') }}</td><td>{{ $movement->product->sku }}</td><td>{{ $movement->warehouse->code }}</td><td>{{ str($movement->type->value)->headline() }}</td><td>{{ $movement->source_reference }}</td><td class="text-right">{{ $movement->quantity }}</td>@if($canViewCost)<td class="text-right">{{ $movement->unit_cost }}</td>@endif @if($canViewValuation)<td class="pr-4 text-right">{{ $movement->total_cost }}</td>@endif</tr>@empty<tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No posted movements match this range.</td></tr>@endforelse</tbody></table></div>
    <div class="mt-4">{{ $ledger->links() }}</div>

    <h2 class="mt-7 text-lg font-semibold">Damaged, counted, and adjusted stock</h2>
    <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200"><table class="min-w-full text-sm"><thead><tr><th class="px-4 py-3 text-left">Date</th><th class="text-left">Product</th><th>Warehouse</th><th>Type</th><th class="pr-4 text-right">Quantity</th></tr></thead><tbody>@forelse($adjustments as $movement)<tr class="border-t border-slate-200"><td class="px-4 py-3">{{ $movement->movement_date->format('M d, Y') }}</td><td>{{ $movement->product->sku }} — {{ $movement->product->name }}</td><td class="text-center">{{ $movement->warehouse->code }}</td><td class="text-center">{{ str($movement->type->value)->headline() }}</td><td class="pr-4 text-right">{{ $movement->quantity }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No adjusted stock movements.</td></tr>@endforelse</tbody></table></div>
</x-app-layout>
