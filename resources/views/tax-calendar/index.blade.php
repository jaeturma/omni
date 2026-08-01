<x-app-layout title="Tax Compliance Calendar">
    <x-page-header title="Tax Compliance Calendar" description="Registered obligations, preparation status, reviewers, and traceable deadline adjustments." />
    <div class="grid gap-6">
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Preparation calendar only. Omni does not file returns or pay taxes through BIR. Confirm every obligation and deadline with current official guidance.</div>
        @can('tax-calendar.generate')
            <form method="POST" action="{{ route('tax-calendar.generate') }}" class="flex flex-wrap items-end gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200">@csrf
                <label class="flex flex-col gap-1 text-sm font-medium">From year<input type="number" name="from_year" min="2026" max="2100" value="{{ old('from_year', 2026) }}" required class="rounded-lg border border-slate-300 px-3 py-2">@error('from_year')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                <label class="flex flex-col gap-1 text-sm font-medium">Through year<input type="number" name="through_year" min="2026" max="2100" value="{{ old('through_year', now()->year + 1) }}" required class="rounded-lg border border-slate-300 px-3 py-2">@error('through_year')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Generate registered obligations</button>
            </form>
        @endcan
        <div class="grid gap-4">
            @forelse ($obligations as $obligation)
                <article class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div><h2 class="font-semibold text-slate-900">{{ $obligation->bir_form_number }} · {{ $obligation->taxPeriod->label }}</h2><p class="text-sm text-slate-600">Official period {{ $obligation->taxPeriod->period_start->toDateString() }} to {{ $obligation->taxPeriod->period_end->toDateString() }} · Transaction capture from {{ $obligation->taxPeriod->capture_start->toDateString() }}</p></div>
                        <div class="text-right text-sm"><strong>{{ str($obligation->status)->headline() }}</strong><div>Due {{ $obligation->effectiveDueDate()->toDateString() }}</div>@if($obligation->effectiveDueDate()->isPast() && !in_array($obligation->status, ['filed','paid','not_applicable']))<div class="font-semibold text-red-700">Reminder: overdue</div>@endif</div>
                    </div>
                    <p class="mt-3 text-sm"><strong>Deadline source:</strong> {{ $obligation->deadline_rule_source }}</p>
                    @can('update', $obligation)
                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <form method="POST" action="{{ route('tax-calendar.update', $obligation) }}" class="grid gap-2 rounded-lg bg-slate-50 p-3">@csrf @method('PATCH')
                                <label class="text-sm font-medium">Status<select name="status" class="mt-1 w-full rounded border border-slate-300 px-2 py-1">@foreach(App\Services\TaxComplianceCalendar::STATUSES as $status)<option value="{{ $status }}" @selected($obligation->status === $status)>{{ str($status)->headline() }}</option>@endforeach</select>@error('status')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                                @can('assignReviewer', $obligation)<label class="text-sm font-medium">Reviewer<select name="assigned_reviewer_id" class="mt-1 w-full rounded border border-slate-300 px-2 py-1"><option value="">Unassigned</option>@foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}" @selected($obligation->assigned_reviewer_id === $reviewer->id)>{{ $reviewer->name }}</option>@endforeach</select></label>@endcan
                                <label class="text-sm font-medium">Notes<textarea name="notes" class="mt-1 w-full rounded border border-slate-300 px-2 py-1">{{ $obligation->notes }}</textarea></label><button class="justify-self-start rounded bg-blue-700 px-3 py-1 text-sm font-semibold text-white">Update obligation</button>
                            </form>
                            <form method="POST" action="{{ route('tax-calendar.deadline-adjustments.store', $obligation) }}" class="grid gap-2 rounded-lg bg-slate-50 p-3">@csrf
                                <label class="text-sm font-medium">Adjusted due date<input type="date" name="adjusted_due_date" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1">@error('adjusted_due_date')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                                <label class="text-sm font-medium">Reason<textarea name="reason" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1"></textarea></label><label class="text-sm font-medium">Official source title<input name="source_title" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1"></label><label class="text-sm font-medium">Official BIR source URL<input type="url" name="source_url" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1">@error('source_url')<span class="text-red-700">{{ $message }}</span>@enderror</label><button class="justify-self-start rounded bg-slate-800 px-3 py-1 text-sm font-semibold text-white">Record adjustment</button>
                            </form>
                        </div>
                    @endcan
                    @if($obligation->deadlineAdjustments->isNotEmpty())<details class="mt-4 text-sm"><summary class="cursor-pointer text-blue-700 underline">Deadline adjustment history</summary><ul class="mt-2 grid gap-1">@foreach($obligation->deadlineAdjustments as $adjustment)<li>{{ $adjustment->previous_due_date->toDateString() }} → {{ $adjustment->adjusted_due_date->toDateString() }} · {{ $adjustment->reason }} · {{ $adjustment->source_title }} · {{ $adjustment->adjustedBy->name }}</li>@endforeach</ul></details>@endif
                </article>
            @empty
                <div class="rounded-2xl bg-white p-8 text-center text-slate-500 ring-1 ring-slate-200">No registered tax obligations generated.</div>
            @endforelse
        </div>
        {{ $obligations->links() }}
    </div>
</x-app-layout>
