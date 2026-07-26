<x-app-layout title="Source Postings">
    <x-page-header title="Source Postings" description="Automatic journal posting attempts, links, and retryable failures." />

    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead><tr><th class="px-5 py-3 text-left">Source</th><th class="px-3 py-3 text-left">Status</th><th class="px-3 py-3 text-left">Journal</th><th class="px-3 py-3 text-left">Attempts</th><th class="px-3 py-3 text-left">Failure</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
            <tbody>
                @forelse($postings as $posting)
                    <tr class="border-t border-slate-200">
                        <td class="px-5 py-3">{{ str($posting->source_type->value)->replace('_', ' ')->title() }} #{{ $posting->source_id }}</td>
                        <td class="px-3 py-3">{{ str($posting->status)->title() }}</td>
                        <td class="px-3 py-3">{{ $posting->journalEntry?->journal_number ?? '—' }}</td>
                        <td class="px-3 py-3">{{ $posting->attempt_count }}</td>
                        <td class="max-w-md px-3 py-3 text-red-700">
                            @can('source-posting.view-errors')
                                {{ $posting->failure_reason }}
                            @else
                                {{ $posting->status === 'failed' ? 'Posting failed; contact an authorized bookkeeper.' : '' }}
                            @endcan
                        </td>
                        <td class="px-5 py-3 text-right">
                            @can('retry', $posting)
                                <form method="POST" action="{{ route('source-postings.retry', $posting) }}">@csrf<button class="font-semibold text-blue-700">Retry</button></form>
                            @endcan
                            @can('rebuildLink', $posting)
                                <form method="POST" action="{{ route('source-postings.rebuild-link', $posting) }}">@csrf<button class="font-semibold text-blue-700">Rebuild link</button></form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">No source posting attempts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $postings->links() }}</div>
</x-app-layout>
