<x-app-layout title="Posting Rules">
    <x-page-header title="Posting Rules" description="Resolve operational sources to effective-dated debit and credit accounts with explicit fallbacks." />

    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="grid gap-1 text-sm">Source type
                <select name="source_type" class="rounded-lg border-slate-300">
                    <option value="">All source types</option>
                    @foreach($sourceTypes as $sourceType)
                        <option value="{{ $sourceType->value }}" @selected(request('source_type') === $sourceType->value)>{{ str($sourceType->value)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Filter</button>
        </form>
        @can('create', \App\Models\PostingRule::class)
            <a href="{{ route('posting-rules.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Create rule</a>
        @endcan
    </div>

    @can('preview', \App\Models\PostingRule::class)
        <form method="POST" action="{{ route('posting-rules.preview') }}" class="mb-6 grid gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200 md:grid-cols-4">
            @csrf
            <h2 class="font-semibold md:col-span-4">Preview a fallback outcome</h2>
            <label class="grid gap-1 text-sm">Source type
                <select name="source_type" required class="rounded-lg border-slate-300">
                    @foreach($sourceTypes as $sourceType)<option value="{{ $sourceType->value }}">{{ str($sourceType->value)->replace('_', ' ')->title() }}</option>@endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm">Posting date<input type="date" name="posting_date" required value="{{ now()->toDateString() }}" class="rounded-lg border-slate-300"></label>
            <label class="grid gap-1 text-sm">Amount<input type="number" name="amount" required min="0.0001" step="0.0001" value="1.0000" class="rounded-lg border-slate-300"></label>
            <div class="flex items-end"><button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Preview fallback</button></div>
            <p class="text-xs text-slate-500 md:col-span-4">This quick preview resolves the explicit fallback. Specific dimensions can be verified through tests/API input and will never create a journal.</p>
        </form>
    @endcan

    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead><tr><th class="px-5 py-3 text-left">Rule</th><th class="px-3 py-3 text-left">Source</th><th class="px-3 py-3 text-left">Debit</th><th class="px-3 py-3 text-left">Credit</th><th class="px-3 py-3 text-left">Effective</th><th class="px-3 py-3 text-left">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
            <tbody>
                @forelse($rules as $rule)
                    <tr class="border-t border-slate-200">
                        <td class="px-5 py-3 font-medium">{{ $rule->name }} @if($rule->specificity() === 0)<span class="ml-1 rounded bg-blue-50 px-2 py-1 text-xs text-blue-700">Fallback</span>@endif</td>
                        <td class="px-3 py-3">{{ str($rule->source_type->value)->replace('_', ' ')->title() }}</td>
                        <td class="px-3 py-3">{{ $rule->debitAccount->code }} — {{ $rule->debitAccount->name }}</td>
                        <td class="px-3 py-3">{{ $rule->creditAccount->code }} — {{ $rule->creditAccount->name }}</td>
                        <td class="px-3 py-3">{{ $rule->effective_from->toDateString() }} to {{ $rule->effective_to?->toDateString() ?? 'Open-ended' }}</td>
                        <td class="px-3 py-3">{{ $rule->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-5 py-3"><div class="flex justify-end gap-2">
                            @can('update', $rule)<a href="{{ route('posting-rules.edit', $rule) }}" class="text-blue-700">Edit</a>@endcan
                            @can($rule->is_active ? 'deactivate' : 'activate', $rule)
                                <form method="POST" action="{{ route('posting-rules.status', $rule) }}">@csrf @method('PATCH')<button class="text-slate-700">{{ $rule->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                            @endcan
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-slate-500">No posting rules found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $rules->links() }}</div>
</x-app-layout>
