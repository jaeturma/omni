<x-app-layout title="Accounting Period Controls">
    <x-page-header title="{{ $period->name }} — Accounting Controls" description="{{ $period->fiscalYear->name }} · {{ $period->starts_on->toDateString() }} to {{ $period->ends_on->toDateString() }}" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('fiscal-years.index') }}" class="text-sm">← Fiscal years</a>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold capitalize">{{ $period->status }}</span>
    </div>
    @error('status')<div class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">{{ $message }}</div>@enderror

    <section class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
        <h2 class="text-lg font-semibold">Pre-close checklist</h2>
        <p class="mt-1 text-sm text-slate-600">Critical failures must be resolved. Only open adjustment journals may be explicitly overridden with documented notes.</p>
        <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="py-2 text-left">Check</th><th>Severity</th><th class="text-center">Findings</th><th class="text-left">Result</th></tr></thead><tbody>
            @foreach($checklist ?? [] as $key => $item)<tr class="border-t"><td class="py-3">{{ $item['label'] }}</td><td class="text-center capitalize">{{ $item['severity'] }}</td><td class="text-center">{{ $item['count'] }}</td><td class="{{ $item['passed'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $item['passed'] ? 'Passed' : ($item['overrideable'] ? 'Warning — override permitted' : 'Blocking') }}</td></tr>@endforeach
        </tbody></table></div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @if($period->status === 'open')
            @can('close', $period)<form method="POST" action="{{ route('fiscal-periods.status.update', $period) }}" class="grid gap-4 rounded-xl bg-white p-5 ring-1 ring-slate-200">@csrf @method('PATCH')
                <input type="hidden" name="status" value="closed"><input type="hidden" name="lock_version" value="{{ $period->lock_version }}">
                <h2 class="font-semibold">Close period</h2>
                <label class="grid gap-1 text-sm">Close notes<textarea name="notes" rows="3" class="rounded-lg border-slate-300">{{ old('notes') }}</textarea></label>
                @if(($checklist['open_adjustments']['count'] ?? 0) > 0)<label class="flex items-start gap-2 text-sm"><input type="checkbox" name="override_open_adjustments" value="1" class="mt-1 rounded border-slate-300">Override the open-adjustment warning; notes above document the approved reason.</label>@endif
                <button class="w-fit rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Confirm period close</button>
            </form>@endcan
        @elseif($period->status === 'closed')
            @can('lock', $period)<form method="POST" action="{{ route('fiscal-periods.status.update', $period) }}" class="grid gap-4 rounded-xl bg-white p-5 ring-1 ring-slate-200">@csrf @method('PATCH')
                <input type="hidden" name="status" value="locked"><input type="hidden" name="lock_version" value="{{ $period->lock_version }}">
                <h2 class="font-semibold">Lock after final review</h2><label class="grid gap-1 text-sm">Lock notes<textarea name="notes" rows="3" class="rounded-lg border-slate-300"></textarea></label>
                <button class="w-fit rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Confirm final lock</button>
            </form>@endcan
        @endif
        @if(in_array($period->status, ['closed', 'locked'], true))
            @can('reopen', $period)<form method="POST" action="{{ route('fiscal-periods.status.update', $period) }}" class="grid gap-4 rounded-xl bg-amber-50 p-5 ring-1 ring-amber-200">@csrf @method('PATCH')
                <input type="hidden" name="status" value="open"><input type="hidden" name="lock_version" value="{{ $period->lock_version }}">
                <h2 class="font-semibold">Elevated reopen</h2><label class="grid gap-1 text-sm">Required reason<textarea name="notes" rows="3" required class="rounded-lg border-slate-300"></textarea>@error('notes')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                <button class="w-fit rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Confirm reopen</button>
            </form>@endcan
        @endif
    </div>

    <section class="mt-6 rounded-xl bg-white p-5 ring-1 ring-slate-200"><h2 class="text-lg font-semibold">Audit history</h2><div class="mt-3 grid gap-3">
        @forelse($period->events as $event)<div class="rounded-lg border border-slate-200 p-3 text-sm"><div class="flex flex-wrap justify-between gap-2"><span class="font-semibold capitalize">{{ $event->action }} · {{ $event->from_status }} → {{ $event->to_status }}</span><span>{{ $event->performed_at->toDayDateTimeString() }} by {{ $event->performedBy->name }}</span></div>@if($event->notes)<p class="mt-2 text-slate-700">{{ $event->notes }}</p>@endif</div>@empty<p class="text-sm text-slate-600">No accounting-period transitions recorded.</p>@endforelse
    </div></section>
</x-app-layout>
