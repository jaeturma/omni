<x-app-layout title="Fiscal Years">
    <x-page-header title="Fiscal Years and Periods" description="Create fiscal calendars and control monthly accounting periods." />

    <form method="POST" action="{{ route('fiscal-years.store') }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @csrf
        <div class="grid gap-4 md:grid-cols-4">
            <label class="flex flex-col gap-1 text-sm font-medium">Name
                <input name="name" value="{{ old('name', 'FY 2026') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
                @error('name')<span class="text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium">Starts on
                <input type="date" name="starts_on" value="{{ old('starts_on', '2026-05-01') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
                @error('starts_on')<span class="text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium">Ends on
                <input type="date" name="ends_on" value="{{ old('ends_on', '2026-12-31') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
                @error('ends_on')<span class="text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="flex items-center gap-2 self-end py-2 text-sm font-medium">
                <input type="hidden" name="is_current" value="0"><input type="checkbox" name="is_current" value="1" @checked(old('is_current', true))> Current fiscal year
            </label>
        </div>
        <button class="mt-5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Create fiscal year</button>
    </form>

    <div class="mt-6 flex flex-col gap-6">
        @forelse ($years as $year)
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
                    <div><h2 class="font-semibold">{{ $year->name }}</h2><p class="text-sm text-slate-600">{{ $year->starts_on->toFormattedDateString() }} – {{ $year->ends_on->toFormattedDateString() }}</p></div>
                    @if ($year->is_current)<span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">Current</span>@endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-6 py-3">Period</th><th class="px-6 py-3">Dates</th><th class="px-6 py-3">Quarter</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Actions</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($year->periods as $period)
                                <tr><td class="px-6 py-3 font-medium">{{ $period->name }}</td><td class="px-6 py-3">{{ $period->starts_on->toDateString() }} – {{ $period->ends_on->toDateString() }}</td><td class="px-6 py-3">Q{{ $period->calendar_quarter }}</td><td class="px-6 py-3 capitalize">{{ $period->status }}</td><td class="px-6 py-3">
                                    <div class="flex gap-2">
                                        @if ($period->status === 'open')<form method="POST" action="{{ route('fiscal-periods.status.update', $period) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="closed"><button class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold">Close</button></form>@endif
                                        @if ($period->status === 'closed')<form method="POST" action="{{ route('fiscal-periods.status.update', $period) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="locked"><button class="rounded-lg bg-slate-800 px-3 py-1.5 font-semibold text-white">Lock</button></form>@endif
                                    </div>
                                </td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <p class="rounded-xl bg-white p-6 text-sm text-slate-600 ring-1 ring-slate-200">No fiscal years have been created.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $years->links() }}</div>
</x-app-layout>
