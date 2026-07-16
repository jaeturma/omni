@if (session('success'))
    <div {{ $attributes->merge(['class' => 'mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900']) }} role="status">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div {{ $attributes->merge(['class' => 'mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900']) }} role="alert">
        {{ session('error') }}
    </div>
@endif
