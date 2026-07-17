<div class="mt-6 grid gap-5 xl:grid-cols-2">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="font-semibold text-slate-900">Source traceability</h2>
        <div class="mt-4 flex flex-wrap gap-2" data-testid="sales-traceability">
            @foreach($links as $link)<a href="{{ $link['url'] }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm hover:border-blue-400"><span class="font-medium">{{ $link['label'] }}</span> {{ $link['number'] }} <span class="ml-1 text-xs capitalize text-slate-500">{{ str_replace('_', ' ', $link['status']) }}</span></a>@endforeach
        </div>
    </section>
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between gap-3"><h2 class="font-semibold text-slate-900">Attachments</h2><span class="text-xs text-slate-500">Private files</span></div>
        <div class="mt-4 space-y-3">
            @forelse($record->salesAttachments as $attachment)
                <div class="rounded-lg border border-slate-200 p-3 text-sm"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="font-medium">{{ $attachment->document_type }} — {{ $attachment->original_filename }}</p><p class="text-xs text-slate-500">{{ $attachment->document_date->format('M d, Y') }} · {{ number_format($attachment->file_size / 1024, 1) }} KB · uploaded by {{ $attachment->uploader->name }}</p><p class="mt-1 break-all font-mono text-[11px] text-slate-400">SHA-256 {{ $attachment->file_hash }}</p></div>@can('view', $attachment)<a href="{{ route('sales-attachments.download', $attachment) }}" class="font-medium text-blue-700">Download</a>@endcan</div>
                    @can('delete', $attachment)<form method="POST" action="{{ route('sales-attachments.destroy', $attachment) }}" class="mt-3 flex flex-wrap gap-2">@csrf @method('DELETE')<input name="deletion_reason" required maxlength="500" placeholder="Deletion reason" class="min-w-52 flex-1 rounded-lg border-slate-300 text-sm"><button class="rounded-lg border border-red-300 px-3 py-2 font-medium text-red-700" onclick="return confirm('Delete this attachment?')">Delete</button></form>@endcan
                </div>
            @empty<p class="text-sm text-slate-500">No attachments uploaded.</p>@endforelse
        </div>
        @can('create', \App\Models\SalesAttachment::class)
            <form method="POST" enctype="multipart/form-data" action="{{ route('sales-attachments.store', [$record->getMorphClass(), $record->getKey()]) }}" class="mt-5 grid gap-3 border-t border-slate-200 pt-5 sm:grid-cols-2">@csrf
                <label class="grid gap-1 text-sm sm:col-span-2">File <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" class="rounded-lg border border-slate-300 p-2"></label><label class="grid gap-1 text-sm">Document type <input name="document_type" value="{{ old('document_type') }}" required maxlength="100" class="rounded-lg border-slate-300"></label><label class="grid gap-1 text-sm">Document date <input type="date" name="document_date" value="{{ old('document_date', now()->toDateString()) }}" required class="rounded-lg border-slate-300"></label><label class="grid gap-1 text-sm">Reference number <input name="reference_number" value="{{ old('reference_number') }}" maxlength="100" class="rounded-lg border-slate-300"></label><label class="grid gap-1 text-sm">Notes <input name="notes" value="{{ old('notes') }}" maxlength="2000" class="rounded-lg border-slate-300"></label>@error('file')<p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p>@enderror<button class="w-fit rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white sm:col-span-2">Upload attachment</button>
            </form>
        @endcan
    </section>
</div>
