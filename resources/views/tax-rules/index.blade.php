<x-app-layout title="Tax Compliance Rules">
    <x-page-header title="Tax Compliance Rules" description="Effective-dated preparation rules reviewed against official BIR references." />
    <div class="grid gap-6">
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">These settings support preparation worksheets only. Confirm form applicability, deadlines, rates, and references against the Certificate of Registration and current BIR guidance.</div>
        @can('create', App\Models\TaxComplianceRule::class)
            <section>
                <h2 class="mb-3 text-lg font-semibold text-slate-900">Add rule</h2>
                <x-tax-rule-form :action="route('tax-rules.store')" />
            </section>
        @endcan
        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-700"><tr><th class="p-3">Form / tax</th><th class="p-3">Effective period</th><th class="p-3">Applicability</th><th class="p-3">Reference review</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($rules as $rule)
                        <tr class="border-t border-slate-200 align-top">
                            <td class="p-3"><strong>{{ $rule->bir_form_number }}</strong><div>{{ $rule->form_title }}</div><div class="text-slate-500">{{ str($rule->tax_type)->headline() }}</div></td>
                            <td class="p-3">{{ $rule->effective_from->toDateString() }} — {{ $rule->effective_to?->toDateString() ?? 'Open-ended' }}</td>
                            <td class="p-3">{{ str($rule->taxpayer_applicability)->headline() }}<div class="text-slate-500">{{ str($rule->registration_applicability)->headline() }}</div></td>
                            <td class="p-3">
                                <a href="{{ $rule->official_reference_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline">{{ $rule->official_reference_title }}</a>
                                <div>{{ $rule->last_reviewed_on->toDateString() }} by {{ $rule->reviewer->name }}</div>
                                @if ($rule->referenceReviewIsStale())<span class="font-semibold text-amber-700">Official-reference review is stale.</span>@endif
                            </td>
                            <td class="p-3">{{ $rule->active ? 'Active' : 'Inactive' }}@if($rule->used_at)<div class="text-slate-500">Used; changes create a successor</div>@endif</td>
                            <td class="p-3"><div class="flex flex-wrap gap-2">
                                @can('update', $rule)<a href="{{ route('tax-rules.edit', $rule) }}" class="text-blue-700 underline">Edit</a>@endcan
                                @if ($rule->active)
                                    @can('deactivate', $rule)<form method="POST" action="{{ route('tax-rules.deactivate', $rule) }}">@csrf @method('PATCH')<button class="text-red-700 underline">Deactivate</button></form>@endcan
                                @else
                                    @can('activate', $rule)<form method="POST" action="{{ route('tax-rules.activate', $rule) }}">@csrf @method('PATCH')<button class="text-emerald-700 underline">Activate</button></form>@endcan
                                @endif
                            </div>
                            @can('review', $rule)
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-blue-700 underline">Record reference review</summary>
                                    <form method="POST" action="{{ route('tax-rules.review', $rule) }}" class="mt-2 grid min-w-72 gap-2">@csrf @method('PATCH')
                                        <input name="official_reference_title" value="{{ $rule->official_reference_title }}" aria-label="Official reference title" required class="rounded border border-slate-300 px-2 py-1">
                                        <input type="url" name="official_reference_url" value="{{ $rule->official_reference_url }}" aria-label="Official reference URL" required class="rounded border border-slate-300 px-2 py-1">
                                        <input type="date" name="last_reviewed_on" value="{{ now()->toDateString() }}" aria-label="Last reviewed on" required class="rounded border border-slate-300 px-2 py-1">
                                        <textarea name="reviewer_notes" aria-label="Reviewer notes" placeholder="Reviewer notes" class="rounded border border-slate-300 px-2 py-1">{{ $rule->reviewer_notes }}</textarea>
                                        <button class="justify-self-start rounded bg-blue-700 px-3 py-1 font-semibold text-white">Save review</button>
                                    </form>
                                </details>
                            @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-slate-500">No tax compliance rules configured.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $rules->links() }}</div>
        </section>
    </div>
</x-app-layout>
