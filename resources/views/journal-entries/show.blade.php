<x-app-layout :title="$entry->journal_number">
    <x-page-header :title="$entry->journal_number" :description="$entry->description" />
    <div class="mb-5 flex flex-wrap gap-3">@can('update',$entry)<a href="{{ route('journal-entries.edit',$entry) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Edit draft</a>@endcan @can('post',$entry)<form method="POST" action="{{ route('journal-entries.transition',$entry) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="posted"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Post journal</button></form>@endcan</div>
    <dl class="mb-5 grid gap-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-4"><div><dt class="text-xs uppercase text-slate-500">Status</dt><dd class="font-semibold capitalize">{{ $entry->status->value }}</dd></div><div><dt class="text-xs uppercase text-slate-500">Journal date</dt><dd>{{ $entry->journal_date->format('Y-m-d') }}</dd></div><div><dt class="text-xs uppercase text-slate-500">Period</dt><dd>{{ $entry->fiscalPeriod->name }}</dd></div><div><dt class="text-xs uppercase text-slate-500">Reference</dt><dd>{{ $entry->reference_number ?: '—' }}</dd></div></dl>
    @if($entry->reversalEntry || $entry->reversedEntry || $entry->correctionEntry || $entry->correctedEntry)
        <div class="mb-5 rounded-2xl bg-blue-50 p-5 text-sm ring-1 ring-blue-200">
            @if($entry->reversalEntry)<p>Reversed by <a class="font-semibold text-blue-700" href="{{ route('journal-entries.show', $entry->reversalEntry) }}">{{ $entry->reversalEntry->journal_number }}</a>.</p>@endif
            @if($entry->reversedEntry)<p>Reversal of <a class="font-semibold text-blue-700" href="{{ route('journal-entries.show', $entry->reversedEntry) }}">{{ $entry->reversedEntry->journal_number }}</a>.</p>@endif
            @if($entry->correctionEntry)<p>Corrected by <a class="font-semibold text-blue-700" href="{{ route('journal-entries.show', $entry->correctionEntry) }}">{{ $entry->correctionEntry->journal_number }}</a>.</p>@endif
            @if($entry->correctedEntry)<p>Correction of <a class="font-semibold text-blue-700" href="{{ route('journal-entries.show', $entry->correctedEntry) }}">{{ $entry->correctedEntry->journal_number }}</a>.</p>@endif
            @if($entry->reversal_reason)<p class="mt-1">Reason: {{ $entry->reversal_reason }}</p>@endif
        </div>
    @endif
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="px-6 py-3 text-left">Line</th><th class="px-6 py-3 text-left">Account</th><th class="px-6 py-3 text-left">Description</th><th class="px-6 py-3 text-right">Debit</th><th class="px-6 py-3 text-right">Credit</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($entry->lines as $line)<tr><td class="px-6 py-4">{{ $line->line_number }}</td><td class="px-6 py-4">{{ $line->account->code }} — {{ $line->account->name }}</td><td class="px-6 py-4">{{ $line->description }}</td><td class="px-6 py-4 text-right font-mono">{{ $line->debit }}</td><td class="px-6 py-4 text-right font-mono">{{ $line->credit }}</td></tr>@endforeach</tbody><tfoot class="bg-slate-50 font-semibold"><tr><td colspan="3" class="px-6 py-3 text-right">Totals</td><td class="px-6 py-3 text-right font-mono">{{ $entry->total_debit }}</td><td class="px-6 py-3 text-right font-mono">{{ $entry->total_credit }}</td></tr></tfoot></table></div>
    @can('void',$entry)<form method="POST" action="{{ route('journal-entries.transition',$entry) }}" class="mt-5 flex max-w-xl gap-3">@csrf @method('PATCH')<input type="hidden" name="status" value="voided"><input name="void_reason" required placeholder="Reason for voiding" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700">Void</button></form>@endcan
    @can('reverse',$entry)
        <form method="POST" action="{{ route('journal-entries.reverse',$entry) }}" class="mt-5 grid max-w-3xl gap-3 rounded-2xl bg-white p-5 ring-1 ring-slate-200 md:grid-cols-3">
            @csrf
            <h2 class="font-semibold md:col-span-3">Reverse journal</h2>
            <label class="grid gap-1 text-sm">Reversal date<input type="date" name="reversal_date" required value="{{ $entry->journal_date->toDateString() }}" class="rounded-lg border-slate-300"></label>
            <label class="grid gap-1 text-sm">Open period<select name="fiscal_period_id" required class="rounded-lg border-slate-300">@foreach($openPeriods as $period)<option value="{{ $period->id }}" @selected($period->id === $entry->fiscal_period_id)>{{ $period->name }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-sm md:col-span-3">Reason<textarea name="reason" required class="rounded-lg border-slate-300"></textarea></label>
            @can('autoReverse',$entry)<label class="flex items-center gap-2 text-sm md:col-span-3"><input type="checkbox" name="auto_reverse" value="1"> Auto-reversal in a future open period</label>@endcan
            <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 md:col-span-3">Post reversal</button>
        </form>
    @endcan
    @can('correct',$entry)
        <form method="POST" action="{{ route('journal-entries.correct',$entry) }}" class="mt-5 grid max-w-3xl gap-3 rounded-2xl bg-white p-5 ring-1 ring-slate-200 md:grid-cols-3">
            @csrf
            <h2 class="font-semibold md:col-span-3">Correct journal</h2>
            <label class="grid gap-1 text-sm">Correction date<input type="date" name="correction_date" required value="{{ $entry->journal_date->toDateString() }}" class="rounded-lg border-slate-300"></label>
            <label class="grid gap-1 text-sm">Open period<select name="fiscal_period_id" required class="rounded-lg border-slate-300">@foreach($openPeriods as $period)<option value="{{ $period->id }}" @selected($period->id === $entry->fiscal_period_id)>{{ $period->name }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-sm md:col-span-3">Reason<textarea name="reason" required class="rounded-lg border-slate-300"></textarea></label>
            <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white md:col-span-3">Reverse and create correcting draft</button>
        </form>
    @endcan
</x-app-layout>
