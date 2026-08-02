<x-app-layout title="Sales Tax Reconciliation">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Sales and receipt tax reconciliation</h1>
            <p class="mt-1 text-sm text-slate-600">Compare operational sales, receipts, withholding, and posted ledger revenue. Figures are preparation worksheets for review.</p>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600"><tr><th class="px-4 py-3">Period</th><th class="px-4 py-3">Form</th><th class="px-4 py-3">Difference</th><th class="px-4 py-3">Critical items</th><th class="px-4 py-3">Generated</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($obligations as $obligation)
                            <tr><td class="px-4 py-3 font-medium text-slate-900">{{ $obligation->taxPeriod->label }}</td><td class="px-4 py-3">{{ $obligation->bir_form_number }}</td><td class="px-4 py-3">{{ $obligation->reconciliation ? number_format((float) $obligation->reconciliation->difference, 4) : 'Not generated' }}</td><td class="px-4 py-3">{{ $obligation->reconciliation?->critical_difference_count ?? '—' }}</td><td class="px-4 py-3">{{ $obligation->reconciliation?->generated_at?->format('M j, Y g:i A') ?? '—' }}</td><td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    @if($obligation->reconciliation)<a class="rounded-lg border border-slate-300 px-3 py-2 font-medium text-slate-700" href="{{ route('tax-reconciliations.show', $obligation->reconciliation) }}">View</a>@endif
                                    @can('tax-reconciliation.adjust')<form method="POST" action="{{ route('tax-reconciliations.generate', $obligation) }}">@csrf<button class="rounded-lg bg-slate-900 px-3 py-2 font-medium text-white">{{ $obligation->reconciliation ? 'Refresh' : 'Generate' }}</button></form>@endcan
                                </div>
                            </td></tr>
                        @empty<tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Generate tax-calendar obligations first.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $obligations->links() }}</div>
        </div>
    </div>
</x-app-layout>
