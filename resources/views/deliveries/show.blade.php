<x-app-layout title="Delivery">
    <x-page-header title="{{ $delivery->delivery_number ?? 'Draft #'.$delivery->id }}" description="{{ ucfirst($delivery->status->value) }} delivery for {{ $delivery->customer_name }}" />
    <div class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
        <p>Order: {{ $delivery->salesOrder->sales_order_number }} · PO: {{ $delivery->customer_po_number ?: '—' }}</p>
        <p class="mt-2 whitespace-pre-line">{{ $delivery->delivery_address }}</p>
        <table class="mt-5 w-full text-sm"><thead><tr><th class="text-left">Item</th><th>Quantity</th></tr></thead><tbody>
        @foreach ($delivery->lines as $line)
            <tr><td>{{ $line->sku }} — {{ $line->description }}</td><td class="text-center">{{ $line->delivered_quantity }} {{ $line->uom_code }}</td></tr>
        @endforeach
        </tbody></table>
    </div>
    <div class="mt-5 flex flex-wrap gap-3">
        @can('print', $delivery)<a href="{{ route('deliveries.print', $delivery) }}" class="rounded-lg border px-4 py-2">Print</a>@endcan
        @if ($delivery->status === \App\Enums\DeliveryStatus::Draft) @can('release', $delivery)<form method="POST" action="{{ route('deliveries.transition', $delivery) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="released"><button class="rounded-lg bg-blue-700 px-4 py-2 text-white">Release</button></form>@endcan @endif
        @if ($delivery->status === \App\Enums\DeliveryStatus::Released) @can('release', $delivery)<form method="POST" action="{{ route('deliveries.transition', $delivery) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="status" value="delivered"><input name="received_by_name" required placeholder="Received by" class="rounded-lg border px-3 py-2"><input type="datetime-local" name="received_at" required class="rounded-lg border px-3 py-2"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-white">Mark delivered</button></form>@endcan @endif
        @if ($delivery->status === \App\Enums\DeliveryStatus::Delivered) @can('accept', $delivery)<form method="POST" action="{{ route('deliveries.transition', $delivery) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="accepted"><input name="acceptance_notes" placeholder="Acceptance notes" class="rounded-lg border px-3 py-2"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-white">Accept</button></form>@endcan @endif
        @can('cancel', $delivery) @if ($delivery->status !== \App\Enums\DeliveryStatus::Accepted && $delivery->status !== \App\Enums\DeliveryStatus::Cancelled)<form method="POST" action="{{ route('deliveries.transition', $delivery) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><input name="reason" required placeholder="Cancellation reason" class="rounded-lg border px-3 py-2"><button class="rounded-lg border border-red-300 px-4 py-2 text-red-700">Cancel</button></form>@endif @endcan
    </div>
<x-sales-record-panel :record="$delivery" />
</x-app-layout>
