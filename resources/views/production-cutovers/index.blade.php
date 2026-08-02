<x-app-layout title="Production cutover">
    <x-page-header title="Production cutover" description="Controlled opening-balance reconciliation, approval, backup, and activation evidence." />

    @can('create', App\Models\ProductionCutover::class)
        <form method="POST" action="{{ route('production-cutovers.store') }}" class="grid gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 md:grid-cols-2">
            @csrf
            <div><x-input-label for="cutover_date" value="Cutover date" /><x-text-input id="cutover_date" name="cutover_date" type="date" class="mt-1 block w-full" :value="old('cutover_date')" required /><x-input-error :messages="$errors->get('cutover_date')" class="mt-1" /></div>
            <div><x-input-label for="backup_run_id" value="Verified pre-activation backup" /><select id="backup_run_id" name="backup_run_id" class="mt-1 block w-full rounded-lg border-slate-300" required><option value="">Select backup</option>@foreach($backups as $backup)<option value="{{ $backup->id }}" @selected(old('backup_run_id') == $backup->id)>#{{ $backup->id }} {{ str($backup->backup_class)->headline() }} — {{ $backup->verified_at?->format('M d, Y H:i') }}</option>@endforeach</select><x-input-error :messages="$errors->get('backup_run_id')" class="mt-1" /></div>
            @foreach(['legacy_freeze_reference' => 'Legacy freeze reference', 'source_documents_reference' => 'Opening source documents reference', 'rollback_rehearsal_reference' => 'Rollback rehearsal reference'] as $field => $label)
                <div><x-input-label :for="$field" :value="$label" /><x-text-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field)" required /><x-input-error :messages="$errors->get($field)" class="mt-1" /></div>
            @endforeach
            <div class="grid gap-2 rounded-xl bg-slate-50 p-4 text-sm md:col-span-2 sm:grid-cols-2">
                @foreach(['cash_confirmed' => 'Cash agrees to count and bank statements', 'owner_equity_confirmed' => 'Owner capital, loans, and liabilities agree', 'sequence_confirmed' => 'Document starting numbers are approved', 'tax_control_confirmed' => 'Tax credits, liabilities, and certificates agree'] as $field => $label)
                    <label class="flex items-start gap-2"><input type="checkbox" name="{{ $field }}" value="1" class="mt-1 rounded border-slate-300" @checked(old($field))><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <div class="md:col-span-2"><x-input-label for="notes" value="Notes" /><textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-lg border-slate-300">{{ old('notes') }}</textarea></div>
            <div class="md:col-span-2"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Create controlled draft</button></div>
        </form>
    @endcan

    <div class="mt-5 grid gap-4">
        @forelse($cutovers as $cutover)
            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold">Cutover {{ $cutover->cutover_date->format('M d, Y') }}</h2><p class="text-sm text-slate-500">{{ str($cutover->status)->headline() }} · Backup #{{ $cutover->backup_run_id }}</p></div><div class="flex gap-2">
                    @can('approve', $cutover)<form method="POST" action="{{ route('production-cutovers.approve', $cutover) }}">@csrf @method('PATCH')<button class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white">Approve report</button></form>@endcan
                    @can('activate', $cutover)<form method="POST" action="{{ route('production-cutovers.activate', $cutover) }}" onsubmit="return confirm('Activate this approved production cutover?')">@csrf @method('PATCH')<button class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">Activate</button></form>@endcan
                </div></div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt class="text-slate-500">Legacy freeze</dt><dd>{{ $cutover->legacy_freeze_reference }}</dd></div><div><dt class="text-slate-500">Source documents</dt><dd>{{ $cutover->source_documents_reference }}</dd></div><div><dt class="text-slate-500">Rollback rehearsal</dt><dd>{{ $cutover->rollback_rehearsal_reference }}</dd></div><div><dt class="text-slate-500">Reviewer</dt><dd>{{ $cutover->reviewer?->name ?: 'Pending' }}</dd></div></dl>
                @if($cutover->report_snapshot)<div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm"><p>Trial balance: ₱{{ number_format((float) $cutover->report_snapshot['trial_balance']['debit'], 2) }} debit / ₱{{ number_format((float) $cutover->report_snapshot['trial_balance']['credit'], 2) }} credit</p><p>Opening journals: {{ $cutover->report_snapshot['opening_journal_count'] }} · Inventory batches: {{ $cutover->report_snapshot['inventory_opening_batch_count'] }} · Reconciled controls: {{ $cutover->report_snapshot['subledgers']['checked'] }}</p></div>@endif
            </article>
        @empty
            <p class="rounded-2xl bg-white p-8 text-center text-slate-500 shadow-sm ring-1 ring-slate-200">No production cutover has been prepared.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $cutovers->links() }}</div>
</x-app-layout>
