<x-app-layout title="Tax Profile">
    <x-page-header title="Tax Profile" description="Maintain configurable tax registration and effective-dated rates." />
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Tax-preparation only. Review all settings and outputs with the owner or accountant; Omni Mini-ERP does not file returns with BIR.</div>
    <form method="POST" action="{{ route('tax-profile.update') }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @csrf @method('PUT') <input type="hidden" name="active" value="1">
        <div class="grid gap-4 md:grid-cols-2">
            @foreach (['taxpayer_type','registration_type','vat_status','income_tax_option','filing_frequency','first_filing_period','rdo_code','tin','branch_code','registered_books_type'] as $field)
                <label class="flex flex-col gap-1 text-sm font-medium">{{ str($field)->headline() }}<input name="{{ $field }}" value="{{ old($field, $taxProfile?->{$field}) }}" required class="rounded-lg border border-slate-300 px-3 py-2">@error($field)<span class="text-red-700">{{ $message }}</span>@enderror</label>
            @endforeach
            @foreach (['registration_start_date','percentage_tax_effective_from','percentage_tax_effective_to'] as $field)
                <label class="flex flex-col gap-1 text-sm font-medium">{{ str($field)->headline() }}<input type="date" name="{{ $field }}" value="{{ old($field, $taxProfile?->{$field}?->toDateString()) }}" class="rounded-lg border border-slate-300 px-3 py-2"></label>
            @endforeach
            <label class="flex flex-col gap-1 text-sm font-medium">Percentage tax rate<input name="percentage_tax_rate" value="{{ old('percentage_tax_rate', $taxProfile?->percentage_tax_rate) }}" class="rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="percentage_tax_registered" value="0"><input type="checkbox" name="percentage_tax_registered" value="1" @checked(old('percentage_tax_registered', $taxProfile?->percentage_tax_registered))> Percentage-tax registered</label>
            <label class="flex flex-col gap-1 text-sm font-medium md:col-span-2">Notes<textarea name="notes" class="rounded-lg border border-slate-300 px-3 py-2">{{ old('notes', $taxProfile?->notes) }}</textarea></label>
            <fieldset class="md:col-span-2"><legend class="text-sm font-medium">Applicable BIR forms</legend><div class="mt-2 flex gap-4">@foreach (['2551Q','1701Q','1701A'] as $form)<label class="text-sm"><input type="checkbox" name="forms[]" value="{{ $form }}" @checked($taxProfile?->forms->contains('form_code',$form))> {{ $form }}</label>@endforeach</div></fieldset>
        </div>
        <button class="mt-5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Save tax profile</button>
    </form>
    @if ($taxProfile)
        <form method="POST" action="{{ route('tax-profile.rates.store') }}" class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">@csrf
            <h2 class="font-semibold">Add effective-dated rate</h2><div class="mt-4 grid gap-4 md:grid-cols-4">
                <input name="tax_type" value="percentage_tax" class="rounded-lg border px-3 py-2" required><input name="rate" placeholder="Rate" class="rounded-lg border px-3 py-2" required><input type="date" name="effective_from" class="rounded-lg border px-3 py-2" required><input type="date" name="effective_to" class="rounded-lg border px-3 py-2">
            </div>@error('effective_from')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror<button class="mt-4 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Add rate</button>
        </form>
    @endif
</x-app-layout>
