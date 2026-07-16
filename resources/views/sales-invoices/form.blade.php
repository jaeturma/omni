@php
    $editing = isset($invoice);
    $sourceType = old('source_type', $invoice->source_type ?? ($selectedOrder ? 'order' : ($selectedDelivery ? 'delivery' : 'direct')));
    $formLines = $editing ? $invoice->lines : ($selectedOrder?->lines ?? $selectedDelivery?->lines ?? collect([null]));
@endphp
<x-app-layout title="{{ $editing ? 'Edit' : 'Create' }} Sales Invoice">
    <x-page-header title="{{ $editing ? 'Edit' : 'Create' }} Sales Invoice" description="Amounts are recalculated server-side when saved." />
    @unless ($editing)
        <form method="GET" class="mb-5 grid gap-3 rounded-2xl bg-white p-5 ring-1 ring-slate-200 md:grid-cols-3">
            <label>Order source<select name="sales_order_id" class="mt-1 w-full rounded-lg border px-3 py-2"><option value="">None</option>@foreach ($orders as $order)<option value="{{ $order->id }}">{{ $order->sales_order_number }} — {{ $order->customer_name }}</option>@endforeach</select></label>
            <label>Delivery source<select name="delivery_id" class="mt-1 w-full rounded-lg border px-3 py-2"><option value="">None</option>@foreach ($deliveries as $delivery)<option value="{{ $delivery->id }}">{{ $delivery->delivery_number }} — {{ $delivery->customer_name }}</option>@endforeach</select></label>
            <button class="self-end rounded-lg border px-4 py-2">Load source</button>
        </form>
    @endunless
    <form method="POST" action="{{ $editing ? route('sales-invoices.update', $invoice) : route('sales-invoices.store') }}" class="flex flex-col gap-5">
        @csrf @if ($editing) @method('PUT') @endif
        <input type="hidden" name="source_type" value="{{ $sourceType }}">
        @if ($selectedOrder)<input type="hidden" name="sales_order_id" value="{{ $selectedOrder->id }}">@endif
        @if ($selectedDelivery)<input type="hidden" name="delivery_id" value="{{ $selectedDelivery->id }}">@endif
        @if ($editing)<input type="hidden" name="sales_order_id" value="{{ $invoice->sales_order_id }}"><input type="hidden" name="delivery_id" value="{{ $invoice->delivery_id }}">@endif
        <section class="grid gap-4 rounded-2xl bg-white p-6 ring-1 ring-slate-200 md:grid-cols-2">
            <label>Customer<select name="customer_id" required class="mt-1 w-full rounded-lg border px-3 py-2">@foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $invoice->customer_id ?? $selectedOrder?->customer_id ?? $selectedDelivery?->customer_id) === $customer->id)>{{ $customer->name }}</option>@endforeach</select></label>
            <label>Fiscal period<select name="fiscal_period_id" required class="mt-1 w-full rounded-lg border px-3 py-2">@foreach ($periods as $period)<option value="{{ $period->id }}" @selected(old('fiscal_period_id', $invoice->fiscal_period_id ?? null) === $period->id)>{{ $period->name }}</option>@endforeach</select></label>
            <label>Invoice date<input type="date" name="invoice_date" value="{{ old('invoice_date', isset($invoice) ? $invoice->invoice_date->toDateString() : now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border px-3 py-2"></label>
            <label>Due date<input type="date" name="due_date" value="{{ old('due_date', isset($invoice) ? $invoice->due_date->toDateString() : now()->addDays(30)->toDateString()) }}" required class="mt-1 w-full rounded-lg border px-3 py-2"></label>
            <label>Expected withholding<input type="number" name="expected_withholding_amount" value="{{ old('expected_withholding_amount', $invoice->expected_withholding_amount ?? '0') }}" min="0" step="0.0001" class="mt-1 w-full rounded-lg border px-3 py-2"></label>
            <label>Customer PO<input name="customer_po_number" value="{{ old('customer_po_number', $invoice->customer_po_number ?? $selectedOrder?->customer_po_number ?? $selectedDelivery?->customer_po_number) }}" class="mt-1 w-full rounded-lg border px-3 py-2"></label>
        </section>
        <section class="rounded-2xl bg-white p-6 ring-1 ring-slate-200"><h2 class="mb-4 font-semibold">Invoice lines</h2>
            @foreach ($formLines as $index => $line)
                @php $orderLine = $selectedDelivery ? $line?->salesOrderLine : $line; @endphp
                <div class="grid gap-3 border-t py-3 md:grid-cols-6">
                    @if ($sourceType === 'order')<input type="hidden" name="lines[{{ $index }}][sales_order_line_id]" value="{{ $editing ? $line->sales_order_line_id : $orderLine->id }}">@endif
                    @if ($sourceType === 'delivery')<input type="hidden" name="lines[{{ $index }}][delivery_line_id]" value="{{ $editing ? $line->delivery_line_id : $line->id }}">@endif
                    <input name="lines[{{ $index }}][sku]" value="{{ old("lines.$index.sku", $orderLine?->sku) }}" placeholder="SKU" class="rounded-lg border px-3 py-2">
                    <input name="lines[{{ $index }}][description]" value="{{ old("lines.$index.description", $orderLine?->description) }}" required placeholder="Description" class="rounded-lg border px-3 py-2 md:col-span-2">
                    <input name="lines[{{ $index }}][uom_code]" value="{{ old("lines.$index.uom_code", $orderLine?->uom_code ?? 'UNIT') }}" required placeholder="UOM" class="rounded-lg border px-3 py-2">
                    <input type="hidden" name="lines[{{ $index }}][uom_name]" value="{{ old("lines.$index.uom_name", $orderLine?->uom_name ?? 'Unit') }}">
                    <input type="number" name="lines[{{ $index }}][quantity]" value="{{ old("lines.$index.quantity", $line?->quantity ?? ($selectedDelivery ? $line?->delivered_quantity : $orderLine?->ordered_quantity) ?? '1') }}" min="0.0001" step="0.0001" required class="rounded-lg border px-3 py-2">
                    <input type="number" name="lines[{{ $index }}][unit_price]" value="{{ old("lines.$index.unit_price", $orderLine?->unit_price ?? '0') }}" min="0" step="0.0001" required class="rounded-lg border px-3 py-2">
                    <input type="hidden" name="lines[{{ $index }}][discount_rate]" value="{{ old("lines.$index.discount_rate", $orderLine?->discount_rate ?? '0') }}">
                </div>
            @endforeach
        </section>
        <button class="self-start rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Save draft</button>
    </form>
</x-app-layout>
