<x-app-layout title="Backup status">
    <x-page-header title="Backup status" description="Verification history only. Storage paths and credentials are never displayed." />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><p class="text-sm text-slate-500">Latest verified</p><p class="mt-1 font-semibold">{{ optional($runs->firstWhere('status', 'verified'))->verified_at?->format('M d, Y H:i') ?: 'None' }}</p></section>
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><p class="text-sm text-slate-500">Required RPO</p><p class="mt-1 font-semibold">24 hours</p></section>
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><p class="text-sm text-slate-500">Target RTO</p><p class="mt-1 font-semibold">4 hours</p></section>
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><p class="text-sm text-slate-500">Off-server copies</p><p class="mt-1 font-semibold">{{ $runs->where('offsite_copied', true)->count() }} shown</p></section>
    </div>
    <div class="mt-5 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Started</th><th class="px-4 py-3">Class</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Size</th><th class="px-4 py-3">Hash</th><th class="px-4 py-3">Off-server</th><th class="px-4 py-3">Restore tested</th></tr></thead><tbody class="divide-y divide-slate-200">
        @forelse($runs as $run)<tr><td class="whitespace-nowrap px-4 py-3">{{ $run->started_at->format('M d, Y H:i') }}</td><td class="px-4 py-3">{{ str($run->backup_class)->headline() }}</td><td class="px-4 py-3 font-semibold">{{ str($run->status)->headline() }}</td><td class="px-4 py-3">{{ $run->size_bytes ? number_format($run->size_bytes / 1024, 1).' KB' : '—' }}</td><td class="px-4 py-3 font-mono text-xs">{{ $run->sha256 ? substr($run->sha256, 0, 12).'…' : '—' }}</td><td class="px-4 py-3">{{ $run->offsite_copied ? 'Copied' : 'Not configured' }}</td><td class="px-4 py-3">{{ $run->restore_tested_at?->format('M d, Y H:i') ?: 'Not tested' }}</td></tr>@empty<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No backup runs recorded.</td></tr>@endforelse
        </tbody></table>
    </div><div class="mt-5">{{ $runs->links() }}</div>
</x-app-layout>
