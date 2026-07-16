<x-app-layout title="Document Sequences">
    <x-page-header title="Document Sequences" description="Configure and safely issue controlled document numbers." />

    @error('issue')<p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</p>@enderror

    <form method="POST" action="{{ route('document-sequences.store') }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @csrf
        <h2 class="font-semibold">Create sequence</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3 lg:grid-cols-4">
            <label class="flex flex-col gap-1 text-sm font-medium">Document type<select name="document_type" required class="rounded-lg border border-slate-300 px-3 py-2">@foreach (\App\Models\DocumentSequence::TYPES as $type)<option value="{{ $type }}" @selected(old('document_type') === $type)>{{ str($type)->headline() }}</option>@endforeach</select>@error('document_type')<span class="text-red-700">{{ $message }}</span>@enderror</label>
            <label class="flex flex-col gap-1 text-sm font-medium">Prefix<input name="prefix" value="{{ old('prefix', 'SI-{YYYY}-') }}" class="rounded-lg border border-slate-300 px-3 py-2"><span class="text-xs font-normal text-slate-500">Use {YYYY} for the fiscal year.</span></label>
            <label class="flex flex-col gap-1 text-sm font-medium">Suffix<input name="suffix" value="{{ old('suffix') }}" class="rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="flex flex-col gap-1 text-sm font-medium">Current number<input type="number" min="0" name="current_number" value="{{ old('current_number', 0) }}" required class="rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="flex flex-col gap-1 text-sm font-medium">Padding<input type="number" min="1" max="12" name="padding" value="{{ old('padding', 6) }}" required class="rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="flex flex-col gap-1 text-sm font-medium">Reset rule<select name="reset_rule" class="rounded-lg border border-slate-300 px-3 py-2"><option value="never">Never</option><option value="fiscal_year" @selected(old('reset_rule', 'fiscal_year') === 'fiscal_year')>Fiscal year</option></select></label>
            <label class="flex flex-col gap-1 text-sm font-medium">Fiscal year<select name="fiscal_year_id" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">None</option>@foreach ($fiscalYears as $year)<option value="{{ $year->id }}" @selected((string) old('fiscal_year_id') === (string) $year->id)>{{ $year->name }}</option>@endforeach</select>@error('fiscal_year_id')<span class="text-red-700">{{ $message }}</span>@enderror</label>
            <label class="flex items-center gap-2 self-end py-2 text-sm font-medium"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', true))> Active</label>
        </div>
        <button class="mt-5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Create sequence</button>
    </form>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @forelse ($sequences as $sequence)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-start justify-between gap-3"><div><h2 class="font-semibold">{{ str($sequence->document_type)->headline() }}</h2><p class="text-sm text-slate-600">Next preview: <span class="font-mono font-semibold">{{ $sequence->preview() }}</span></p></div><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $sequence->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">{{ $sequence->active ? 'Active' : 'Inactive' }}</span></div>
                <form method="POST" action="{{ route('document-sequences.update', $sequence) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf @method('PUT')
                    <input type="hidden" name="document_type" value="{{ $sequence->document_type }}">
                    <label class="text-sm">Prefix<input name="prefix" value="{{ $sequence->prefix }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label><label class="text-sm">Suffix<input name="suffix" value="{{ $sequence->suffix }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                    <label class="text-sm">Current number<input type="number" name="current_number" value="{{ $sequence->current_number }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label><label class="text-sm">Padding<input type="number" name="padding" min="1" max="12" value="{{ $sequence->padding }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                    <label class="text-sm">Reset rule<select name="reset_rule" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="never" @selected($sequence->reset_rule === 'never')>Never</option><option value="fiscal_year" @selected($sequence->reset_rule === 'fiscal_year')>Fiscal year</option></select></label><label class="text-sm">Fiscal year<select name="fiscal_year_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">None</option>@foreach ($fiscalYears as $year)<option value="{{ $year->id }}" @selected($sequence->fiscal_year_id === $year->id)>{{ $year->name }}</option>@endforeach</select></label>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($sequence->active)> Active</label><button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Save settings</button>
                </form>
                <form method="POST" action="{{ route('document-sequences.issue', $sequence) }}" class="mt-4">@csrf<button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Issue next number</button></form>
                @if ($sequence->reservations->isNotEmpty())<div class="mt-4 border-t border-slate-200 pt-4"><h3 class="text-sm font-semibold">Recent issuance history</h3><ul class="mt-2 flex flex-col gap-1 font-mono text-sm">@foreach ($sequence->reservations as $reservation)<li>{{ $reservation->document_number }} <span class="font-sans text-xs text-slate-500">{{ $reservation->issued_at->format('Y-m-d H:i') }}</span></li>@endforeach</ul></div>@endif
            </section>
        @empty
            <p class="rounded-xl bg-white p-6 text-sm text-slate-600 ring-1 ring-slate-200">No document sequences configured.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $sequences->links() }}</div>
</x-app-layout>
