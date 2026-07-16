<x-app-layout title="Create Delivery">
    <x-page-header title="Create Delivery" description="Select a confirmed order and record quantities for this delivery." />

    <form method="GET" class="mb-5 flex gap-3 rounded-2xl bg-white p-5 ring-1 ring-slate-200">
        <select name="sales_order_id" required class="rounded-lg border px-3 py-2">
            <option value="">Select sales order</option>
            @foreach ($orders as $order)
                <option value="{{ $order->id }}" @selected($selectedOrder?->id === $order->id)>{{ $order->sales_order_number }} — {{ $order->customer_name }}</option>
            @endforeach
        </select>
        <button class="rounded-lg border px-4 py-2">Load order</button>
    </form>

    @if ($selectedOrder)
        <form method="POST" action="{{ route('deliveries.store') }}" class="flex flex-col gap-5">
            @csrf
            <input type="hidden" name="sales_order_id" value="{{ $selectedOrder->id }}">

            <section class="grid gap-4 rounded-2xl bg-white p-6 ring-1 ring-slate-200 md:grid-cols-2">
                <label class="flex flex-col gap-1">Delivery date<input type="date" name="delivery_date" value="{{ now()->toDateString() }}" required class="rounded-lg border px-3 py-2"></label>
                <label class="flex flex-col gap-1">Warehouse (reference only)<select name="warehouse_id" class="rounded-lg border px-3 py-2"><option value="">None</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label>
                <label class="flex flex-col gap-1 md:col-span-2">Delivery address<textarea name="delivery_address" required class="rounded-lg border px-3 py-2">{{ $selectedOrder->delivery_address }}</textarea></label>
                <label class="flex flex-col gap-1">Recipient<input name="recipient_name" class="rounded-lg border px-3 py-2"></label>
                <label class="flex flex-col gap-1">Contact<input name="recipient_contact" class="rounded-lg border px-3 py-2"></label>
                <label class="flex flex-col gap-1">Inspection reference<input name="inspection_reference" class="rounded-lg border px-3 py-2"></label>
                <label class="flex flex-col gap-1">Notes<textarea name="notes" class="rounded-lg border px-3 py-2"></textarea></label>
            </section>

            <section class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
                <h2 class="mb-4 font-semibold">Delivery quantities</h2>
                @foreach ($selectedOrder->lines as $index => $line)
                    <div class="grid gap-3 border-t py-3 md:grid-cols-3">
                        <input type="hidden" name="lines[{{ $index }}][sales_order_line_id]" value="{{ $line->id }}">
                        <span>{{ $line->sku }} — {{ $line->description }}</span>
                        <span>Remaining: {{ $line->remaining_quantity }} {{ $line->uom_code }}</span>
                        <input type="number" name="lines[{{ $index }}][delivered_quantity]" min="0.0001" max="{{ $line->remaining_quantity }}" step="0.0001" required class="rounded-lg border px-3 py-2">
                    </div>
                @endforeach
            </section>

            <button class="self-start rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Save draft</button>
        </form>
    @endif
</x-app-layout>
