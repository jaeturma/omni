<x-app-layout title="Audit event">
    <x-page-header title="{{ $log->event }}" description="{{ $log->occurred_at->format('M d, Y H:i:s') }} · {{ $log->actor?->name ?: 'System' }}" />
    <section class="mt-5 grid gap-4 rounded-2xl bg-white p-6 text-sm shadow-sm ring-1 ring-slate-200 md:grid-cols-2">
        <div><span class="text-slate-500">Affected record</span><p class="font-mono">{{ $log->subject_type }} #{{ $log->subject_id ?: '—' }}</p></div>
        <div><span class="text-slate-500">Source action</span><p>{{ $log->source_action ?: 'Console/system' }}</p></div>
        <div><span class="text-slate-500">Reason</span><p>{{ $log->reason ?: '—' }}</p></div>
        <div><span class="text-slate-500">Correlation ID</span><p class="font-mono text-xs">{{ $log->correlation_id }}</p></div>
        <div><span class="text-slate-500">IP address</span><p>{{ $showSensitive ? ($log->ip_address ?: '—') : 'Protected' }}</p></div>
        <div><span class="text-slate-500">User agent</span><p class="break-words">{{ $showSensitive ? ($log->user_agent ?: '—') : 'Protected' }}</p></div>
    </section>
    <div class="mt-5 grid gap-5 lg:grid-cols-2"><section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><h2 class="font-semibold">Before</h2><pre class="mt-3 overflow-auto whitespace-pre-wrap text-xs">{{ json_encode($log->before_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></section><section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><h2 class="font-semibold">After</h2><pre class="mt-3 overflow-auto whitespace-pre-wrap text-xs">{{ json_encode($log->after_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></section></div>
</x-app-layout>
