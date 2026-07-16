@props(['title', 'description' => null])

<header {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-1']) }}>
    <h1 class="text-2xl font-bold tracking-tight text-slate-950">{{ $title }}</h1>

    @if ($description)
        <p class="text-sm text-slate-600">{{ $description }}</p>
    @endif
</header>
