<x-app-layout title="Physical Count">
    <x-page-header :title="$count->count_number ?? 'Draft #'.$count->id" :description="$count->warehouse->code.' cutoff '.$count->cutoff_at->format('M d, Y H:i')" />
    <div class="mb-5 flex flex-wrap justify-end gap-3">
        @if($count->status === \App\Enums\PhysicalCountStatus::Draft)
            @can('count', $count)<form method="POST" action="{{ route('physical-counts.transition', $count) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="counting"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Start counting</button></form>@endcan
        @elseif($count->status === \App\Enums\PhysicalCountStatus::Counting)
            @can('count', $count)<form method="POST" action="{{ route('physical-counts.transition', $count) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="submitted"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Submit for review</button></form>@endcan
        @elseif($count->status === \App\Enums\PhysicalCountStatus::Submitted)
            @can('review', $count)<form method="POST" action="{{ route('physical-counts.review', $count) }}">@csrf @method('PATCH')<button class="rounded-lg border border-blue-700 px-4 py-2 text-sm font-semibold text-blue-700">Mark reviewed</button></form><form method="POST" action="{{ route('physical-counts.transition', $count) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="counting"><button class="rounded-lg border px-4 py-2 text-sm font-semibold">Recount</button></form>@endcan
            @can('approve', $count)<form method="POST" action="{{ route('physical-counts.transition', $count) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Approve</button></form>@endcan
        @elseif($count->status === \App\Enums\PhysicalCountStatus::Approved)
            @can('review', $count)<form method="POST" action="{{ route('physical-counts.transition', $count) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="counting"><button class="rounded-lg border px-4 py-2 text-sm font-semibold">Recount</button></form>@endcan
            @can('post', $count)<form method="POST" action="{{ route('physical-counts.transition', $count) }}" onsubmit="return confirm('Post all approved count variances?')">@csrf @method('PATCH')<input type="hidden" name="status" value="posted"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Post variances</button></form>@endcan
        @endif
        @if($count->status !== \App\Enums\PhysicalCountStatus::Voided)
            @can('void', $count)<form method="POST" action="{{ route('physical-counts.transition', $count) }}" class="flex flex-wrap gap-2" onsubmit="return confirm('Void this count through reversal movements?')">@csrf @method('PATCH')<input type="hidden" name="status" value="voided"><input name="reason" required maxlength="1000" placeholder="Void reason" class="rounded-lg border-slate-300"><button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Void</button></form>@endcan
        @endif
    </div>
    <dl class="grid gap-4 rounded-2xl bg-white p-6 text-sm ring-1 ring-slate-200 md:grid-cols-4">
        <div><dt class="text-slate-500">Count date</dt><dd class="font-semibold">{{ $count->count_date->format('M d, Y') }}</dd></div>
        <div><dt class="text-slate-500">Cutoff</dt><dd class="font-semibold">{{ $count->cutoff_at->format('M d, Y H:i') }}</dd></div>
        <div><dt class="text-slate-500">Mode</dt><dd class="font-semibold">{{ $count->blind_count ? 'Blind count' : 'Visible count' }}</dd></div>
        <div><dt class="text-slate-500">Status</dt><dd class="font-semibold">{{ str($count->status->value)->headline() }}</dd></div>
    </dl>
    @php($hideSnapshots = $count->blind_count && $count->status === \App\Enums\PhysicalCountStatus::Counting)
    @if($count->status === \App\Enums\PhysicalCountStatus::Counting)
        @can('count', $count)
            <form method="POST" action="{{ route('physical-counts.record', $count) }}" class="mt-6">@csrf @method('PUT')
                <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200"><table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Product</th>@unless($hideSnapshots)<th class="text-right">System qty</th>@endunless<th class="text-right">Counted qty</th><th class="px-5 text-left">Explanation</th></tr></thead><tbody>
                    @foreach($count->lines as $index => $line)<tr class="border-t"><td class="px-5 py-3">{{ $line->product->sku }} — {{ $line->product->name }}</td>@unless($hideSnapshots)<td class="text-right">{{ number_format((float) $line->system_quantity_snapshot, 4) }}</td>@endunless<td class="py-3 text-right"><input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}"><input name="lines[{{ $index }}][counted_quantity]" type="number" min="0" step="0.0001" required value="{{ old("lines.$index.counted_quantity", $line->counted_quantity) }}" class="w-36 rounded-lg border-slate-300 text-right"></td><td class="px-5 py-3"><input name="lines[{{ $index }}][explanation]" value="{{ old("lines.$index.explanation", $line->explanation) }}" class="w-full rounded-lg border-slate-300" placeholder="Required for a variance"></td></tr>@endforeach
                </tbody></table></div><div class="mt-4 flex justify-end"><button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Save counts</button></div>
            </form>
        @endcan
    @else
        <div class="mt-6 overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200"><table class="min-w-full text-sm"><thead><tr><th class="px-5 py-3 text-left">Product</th><th class="text-right">System qty</th><th class="text-right">Counted qty</th><th class="text-right">Variance</th><th class="text-right">Unit cost</th><th class="text-right">Variance value</th><th class="px-5 text-left">Explanation</th></tr></thead><tbody>
            @foreach($count->lines as $line)<tr class="border-t"><td class="px-5 py-3">{{ $line->product->sku }} — {{ $line->product->name }}</td><td class="text-right">{{ number_format((float) $line->system_quantity_snapshot, 4) }}</td><td class="text-right">{{ $line->counted_quantity === null ? '—' : number_format((float) $line->counted_quantity, 4) }}</td><td class="text-right">{{ $line->variance_quantity === null ? '—' : number_format((float) $line->variance_quantity, 4) }}</td><td class="text-right">PHP {{ number_format((float) $line->unit_cost_snapshot, 4) }}</td><td class="text-right">{{ $line->variance_value === null ? '—' : 'PHP '.number_format((float) $line->variance_value, 4) }}</td><td class="px-5">{{ $line->explanation ?? '—' }}</td></tr>@endforeach
        </tbody></table></div>
    @endif
    @if($count->void_reason)<p class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-800"><strong>Void reason:</strong> {{ $count->void_reason }}</p>@endif
</x-app-layout>
