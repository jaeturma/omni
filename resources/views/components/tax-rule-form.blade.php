@props(['action', 'method' => 'POST', 'rule' => null])

@php
    $value = fn (string $field, mixed $default = null) => old($field, $rule?->{$field} ?? $default);
    $requirements = old('attachment_requirements_text', $rule ? implode("\n", $rule->attachment_requirements ?? []) : '');
@endphp

<form method="POST" action="{{ $action }}" class="grid gap-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:grid-cols-2">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <input type="hidden" name="active" value="{{ $value('active', true) ? 1 : 0 }}">

    <label class="flex flex-col gap-1 text-sm font-medium">Tax type
        <select name="tax_type" required class="rounded-lg border border-slate-300 px-3 py-2">
            @foreach (config('tax_compliance.tax_types') as $key => $label)
                <option value="{{ $key }}" @selected($value('tax_type') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('tax_type')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium">BIR form number
        <input name="bir_form_number" value="{{ $value('bir_form_number') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
        @error('bir_form_number')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium md:col-span-2">Form title
        <input name="form_title" value="{{ $value('form_title') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
        @error('form_title')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium">Taxpayer applicability
        <input name="taxpayer_applicability" value="{{ $value('taxpayer_applicability', 'sole_proprietorship') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
        @error('taxpayer_applicability')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium">Registration applicability
        <select name="registration_applicability" class="rounded-lg border border-slate-300 px-3 py-2">
            @foreach (['any' => 'Registered or non-registered', 'registered' => 'Registered form only', 'not_registered' => 'Non-registered form only'] as $key => $label)
                <option value="{{ $key }}" @selected($value('registration_applicability', 'any') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('registration_applicability')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium">Filing frequency
        <select name="filing_frequency" class="rounded-lg border border-slate-300 px-3 py-2">
            @foreach (config('tax_compliance.filing_frequencies') as $key => $label)
                <option value="{{ $key }}" @selected($value('filing_frequency') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('filing_frequency')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium">Tax rate (%) — optional
        <input name="tax_rate" inputmode="decimal" value="{{ $value('tax_rate') }}" class="rounded-lg border border-slate-300 px-3 py-2">
        @error('tax_rate')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <fieldset class="md:col-span-2"><legend class="text-sm font-medium">Applicable quarters — leave blank when all quarters apply</legend><div class="mt-2 flex flex-wrap gap-4">
        @foreach ([1, 2, 3, 4] as $quarter)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="applicable_quarters[]" value="{{ $quarter }}" @checked(in_array($quarter, old('applicable_quarters', $rule?->applicable_quarters ?? [])))> Q{{ $quarter }}</label>@endforeach
    </div>@error('applicable_quarters')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</fieldset>
    <label class="flex flex-col gap-1 text-sm font-medium">Effective from
        <input type="date" name="effective_from" value="{{ old('effective_from', $rule?->effective_from?->toDateString()) }}" required class="rounded-lg border border-slate-300 px-3 py-2">
        @error('effective_from')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium">Effective to — optional
        <input type="date" name="effective_to" value="{{ old('effective_to', $rule?->effective_to?->toDateString()) }}" class="rounded-lg border border-slate-300 px-3 py-2">
        @error('effective_to')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    @foreach (['tax_base_rule' => 'Tax base rule', 'credit_rule' => 'Deduction or credit treatment', 'deadline_rule' => 'Deadline rule'] as $field => $label)
        <label class="flex flex-col gap-1 text-sm font-medium md:col-span-2">{{ $label }}
            <textarea name="{{ $field }}" required rows="2" class="rounded-lg border border-slate-300 px-3 py-2">{{ $value($field) }}</textarea>
            @error($field)<span class="text-red-700">{{ $message }}</span>@enderror
        </label>
    @endforeach
    <label class="flex flex-col gap-1 text-sm font-medium">Deadline months after period end
        <input type="number" min="0" max="24" name="deadline_months_after_period_end" value="{{ $value('deadline_months_after_period_end') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
        @error('deadline_months_after_period_end')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium">Deadline day of month
        <input type="number" min="1" max="31" name="deadline_day" value="{{ $value('deadline_day') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
        @error('deadline_day')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium md:col-span-2">Attachment requirements — one per line
        <textarea name="attachment_requirements_text" rows="3" class="rounded-lg border border-slate-300 px-3 py-2">{{ $requirements }}</textarea>
        @error('attachment_requirements')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex items-center gap-2 text-sm font-medium md:col-span-2">
        <input type="hidden" name="amendment_supported" value="0">
        <input type="checkbox" name="amendment_supported" value="1" @checked($value('amendment_supported', false))>
        Amended-return workflow is supported by this form
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium md:col-span-2">Official BIR reference title
        <input name="official_reference_title" value="{{ $value('official_reference_title') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
        @error('official_reference_title')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium md:col-span-2">Official BIR reference URL
        <input type="url" name="official_reference_url" value="{{ $value('official_reference_url') }}" required class="rounded-lg border border-slate-300 px-3 py-2">
        @error('official_reference_url')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium">Last reviewed on
        <input type="date" name="last_reviewed_on" value="{{ old('last_reviewed_on', $rule?->last_reviewed_on?->toDateString() ?? now()->toDateString()) }}" required class="rounded-lg border border-slate-300 px-3 py-2">
        @error('last_reviewed_on')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="flex flex-col gap-1 text-sm font-medium md:col-span-2">Reviewer notes
        <textarea name="reviewer_notes" rows="2" class="rounded-lg border border-slate-300 px-3 py-2">{{ $value('reviewer_notes') }}</textarea>
        @error('reviewer_notes')<span class="text-red-700">{{ $message }}</span>@enderror
    </label>
    @if ($rule?->used_at)
        <label class="flex flex-col gap-1 text-sm font-medium md:col-span-2">Reason for superseding this used rule
            <textarea name="change_reason" required rows="2" class="rounded-lg border border-slate-300 px-3 py-2">{{ old('change_reason') }}</textarea>
            @error('change_reason')<span class="text-red-700">{{ $message }}</span>@enderror
        </label>
    @endif
    <div class="md:col-span-2">
        <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">{{ $rule ? 'Save tax rule' : 'Create tax rule' }}</button>
    </div>
</form>
