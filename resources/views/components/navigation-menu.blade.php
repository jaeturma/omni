@props(['groups'])

<div class="flex flex-col gap-2">
    @foreach ($groups as $group => $links)
        @php
            $groupIsActive = collect($links)->contains(fn (array $link) => request()->routeIs($link[1])
                || request()->routeIs(str($link[1])->before('.')->append('.*')->toString()));
        @endphp
        <details class="group" @if ($groupIsActive || $group === 'Workspace') open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-500 hover:bg-slate-50">
                {{ $group }}
                <span class="text-base transition group-open:rotate-90" aria-hidden="true">›</span>
            </summary>
            <div class="mt-1 flex flex-col gap-1 border-l border-slate-200 pl-2">
                @foreach ($links as [$label, $routeName])
                    @php
                        $isActive = request()->routeIs($routeName)
                            || request()->routeIs(str($routeName)->before('.')->append('.*')->toString());
                    @endphp
                    <a href="{{ route($routeName) }}" @class([
                        'rounded-lg px-3 py-2 text-sm font-medium transition',
                        'bg-blue-50 text-blue-800' => $isActive,
                        'text-slate-700 hover:bg-slate-100' => ! $isActive,
                    ]) @if ($isActive) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
