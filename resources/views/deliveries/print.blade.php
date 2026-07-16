<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $delivery->delivery_number ?? 'Draft Delivery' }}</title>@vite(['resources/css/app.css'])</head>
<body class="p-8">
<main class="mx-auto max-w-4xl">
    <h1 class="text-3xl font-bold">Delivery Record</h1>
    <p>{{ $delivery->delivery_number ?? 'DRAFT' }} · {{ $delivery->delivery_date->format('F d, Y') }}</p>
    <h2 class="mt-6 font-bold">{{ $delivery->customer_name }}</h2>
    <p class="whitespace-pre-line">{{ $delivery->delivery_address }}</p>
    <table class="mt-8 w-full">
        <thead><tr><th class="text-left">Description</th><th>Quantity</th></tr></thead>
        <tbody>
        @foreach ($delivery->lines as $line)
            <tr class="border-t"><td class="py-3">{{ $line->description }}</td><td class="text-center">{{ $line->delivered_quantity }} {{ $line->uom_code }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="mt-16 grid grid-cols-2 gap-12"><div class="border-t pt-2">Delivered by</div><div class="border-t pt-2">Received by: {{ $delivery->received_by_name }}</div></div>
</main>
</body>
</html>
