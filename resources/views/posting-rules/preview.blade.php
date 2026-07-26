<x-app-layout title="Posting Preview">
    <x-page-header title="Posting Preview" description="Proposed balanced lines only; no journal entry was created." />
    <div class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
        <dl class="mb-5 grid gap-4 text-sm md:grid-cols-3">
            <div><dt class="text-slate-500">Source</dt><dd class="font-medium">{{ str($preview['source_type'])->replace('_', ' ')->title() }}</dd></div>
            <div><dt class="text-slate-500">Posting date</dt><dd class="font-medium">{{ $preview['date'] }}</dd></div>
            <div><dt class="text-slate-500">Journal count</dt><dd class="font-medium">{{ $journalCount }} (unchanged)</dd></div>
        </dl>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="py-3 text-left">Side</th><th class="py-3 text-left">Required role</th><th class="py-3 text-left">Resolved account</th><th class="py-3 text-right">Amount</th></tr></thead><tbody>@foreach($preview['lines'] as $line)<tr class="border-t border-slate-200"><td class="py-3 font-medium">{{ ucfirst($line['side']) }}</td><td>{{ $line['role'] }}</td><td>{{ $line['account']->code }} — {{ $line['account']->name }}</td><td class="text-right font-mono">₱{{ number_format((float) $line['amount'], 4) }}</td></tr>@endforeach</tbody></table></div>
        <a href="{{ route('posting-rules.index') }}" class="mt-5 inline-block text-sm font-semibold text-blue-700">Back to posting rules</a>
    </div>
</x-app-layout>
