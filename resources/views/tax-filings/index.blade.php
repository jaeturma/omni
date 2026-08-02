<x-app-layout title="Tax Filing History">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Filing, payment, and attachment history</h1>
            <p class="mt-1 text-sm text-slate-600">Manual evidence register only. Omni does not submit returns, make payments, or simulate BIR acknowledgement.</p>
        </div>

        @can('tax-filings.record')
            <form method="POST" action="{{ route('tax-filings.store') }}" class="grid gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <label class="grid gap-1 text-sm sm:col-span-2 lg:col-span-3">Frozen worksheet revision
                    <select name="worksheet_reference" class="rounded-lg border-slate-300" required>
                        <option value="">Select worksheet</option>
                        <optgroup label="2551Q">@foreach($worksheets2551 as $worksheet)<option value="2551q:{{ $worksheet->id }}">2551Q | {{ $worksheet->taxObligation->taxPeriod->label }} | R{{ $worksheet->revision_number }} | PHP {{ $worksheet->total_amount_payable }}</option>@endforeach</optgroup>
                        <optgroup label="1701Q">@foreach($worksheets1701 as $worksheet)<option value="1701q:{{ $worksheet->id }}">1701Q | {{ $worksheet->taxObligation->taxPeriod->label }} | R{{ $worksheet->revision_number }} | PHP {{ $worksheet->total_amount_payable }}</option>@endforeach</optgroup>
                    </select>
                    @error('worksheet_reference')<span class="text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="grid gap-1 text-sm">Filing channel<input name="filing_channel" list="filing-channels" required class="rounded-lg border-slate-300"><datalist id="filing-channels"><option value="eBIRForms"><option value="eFPS"><option value="Authorized agent bank"><option value="Other"></datalist></label>
                <label class="grid gap-1 text-sm">Filing date<input type="date" name="filing_date" required class="rounded-lg border-slate-300"></label>
                <label class="grid gap-1 text-sm">Return reference / confirmation<input name="return_reference" required class="rounded-lg border-slate-300"></label>
                <label class="grid gap-1 text-sm">Amount declared<input name="amount_declared" required inputmode="decimal" class="rounded-lg border-slate-300"></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_amended" value="1"> Amended return</label>
                <label class="grid gap-1 text-sm">Original filing<select name="original_filing_id" class="rounded-lg border-slate-300"><option value="">None</option>@foreach($originalFilings as $original)<option value="{{ $original->id }}">{{ $original->bir_form_number }} | {{ $original->return_reference }}</option>@endforeach</select></label>
                <label class="grid gap-1 text-sm">Amendment reason<input name="amendment_reason" class="rounded-lg border-slate-300"></label>
                <label class="grid gap-1 text-sm lg:col-span-2">Notes<textarea name="notes" class="rounded-lg border-slate-300"></textarea></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirm_manual_filing" value="1" required> Confirm this filing happened outside Omni</label>
                <div class="lg:col-span-3"><button class="rounded-lg bg-blue-700 px-4 py-2 font-medium text-white">Record immutable filing</button></div>
            </form>
        @endcan

        <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Form / period</th><th class="px-4 py-3 text-left">Reference</th><th class="px-4 py-3 text-left">Filed</th><th class="px-4 py-3 text-right">Declared</th><th class="px-4 py-3 text-left">Payment</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($filings as $filing)<tr><td class="px-4 py-3"><a href="{{ route('tax-filings.show', $filing) }}" class="font-medium text-blue-700">{{ $filing->bir_form_number }} | {{ $filing->taxObligation->taxPeriod->label }}</a><div class="text-xs text-slate-500">Revision {{ $filing->worksheet_revision }}{{ $filing->is_amended ? ' | Amended' : '' }}</div></td><td class="px-4 py-3">{{ $filing->return_reference }}</td><td class="px-4 py-3">{{ $filing->filing_date->toDateString() }}<div class="text-xs text-slate-500">{{ $filing->filing_channel }}</div></td><td class="px-4 py-3 text-right">PHP {{ number_format((float) $filing->amount_declared, 4) }}</td><td class="px-4 py-3">{{ str($filing->paymentStatus())->headline() }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No filing history recorded.</td></tr>@endforelse</tbody></table></div>
            <div class="border-t border-slate-200 p-4">{{ $filings->links() }}</div>
        </div>
    </div>
</x-app-layout>
