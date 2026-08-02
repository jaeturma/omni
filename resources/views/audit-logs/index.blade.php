<x-app-layout title="Audit log">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <x-page-header title="Audit log" description="Append-only history of material system activity." />
        @can('export', \App\Models\AuditLog::class)<a href="{{ route('audit-logs.export', request()->query()) }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Export CSV</a>@endcan
    </div>
    <form method="GET" class="mt-5 grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:grid-cols-3 lg:grid-cols-6">
        <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="From date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="To date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="user_id" aria-label="Actor" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All actors</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select>
        <select name="module" aria-label="Module" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ str($module)->headline() }}</option>@endforeach</select>
        <input name="event" value="{{ request('event') }}" placeholder="Event contains" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded-lg border border-blue-700 px-4 py-2 text-sm font-semibold text-blue-700">Apply filters</button>
    </form>
    <div class="mt-5 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Time</th><th class="px-4 py-3">Event</th><th class="px-4 py-3">Actor</th><th class="px-4 py-3">Record</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Request</th></tr></thead>
            <tbody class="divide-y divide-slate-200">@forelse($logs as $log)<tr><td class="whitespace-nowrap px-4 py-3">{{ $log->occurred_at->format('M d, Y H:i:s') }}</td><td class="px-4 py-3"><a href="{{ route('audit-logs.show', $log) }}" class="font-semibold text-blue-700">{{ $log->event }}</a></td><td class="px-4 py-3">{{ $log->actor?->name ?: 'System' }}</td><td class="px-4 py-3 font-mono text-xs">{{ class_basename($log->subject_type) }} #{{ $log->subject_id ?: '—' }}</td><td class="max-w-xs truncate px-4 py-3">{{ $log->reason ?: '—' }}</td><td class="px-4 py-3 text-xs">{{ $showSensitive ? ($log->ip_address ?: '—') : 'Protected' }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No audit events match the filters.</td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="mt-5">{{ $logs->links() }}</div>
</x-app-layout>
