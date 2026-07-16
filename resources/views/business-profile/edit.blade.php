<x-app-layout title="Business Profile">
    <x-page-header title="Business Profile" description="Maintain the registered identity used throughout Omni Mini-ERP." />

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            Please correct the highlighted fields.
        </div>
    @endif

    <form method="POST" action="{{ route('business-profile.update') }}" enctype="multipart/form-data" class="flex flex-col gap-6">
        @csrf
        @method('PUT')

        @php($profile = $businessProfile)
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold">Registration identity</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                @foreach ([
                    'registered_business_name' => 'Registered business name', 'trade_name' => 'Trade name',
                    'proprietor_name' => 'Proprietor name', 'tin' => 'TIN', 'branch_code' => 'Branch code', 'rdo_code' => 'RDO code',
                ] as $field => $label)
                    <label class="flex flex-col gap-2 text-sm font-medium">{{ $label }}
                        <input name="{{ $field }}" value="{{ old($field, $profile?->{$field}) }}" required class="rounded-lg border border-slate-300 px-3 py-2.5">
                        @error($field)<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                @endforeach
                <label class="flex flex-col gap-2 text-sm font-medium">Registration date
                    <input type="date" name="registration_date" value="{{ old('registration_date', $profile?->registration_date?->toDateString()) }}" required class="rounded-lg border border-slate-300 px-3 py-2.5">
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Business start date
                    <input type="date" name="business_start_date" value="{{ old('business_start_date', $profile?->business_start_date?->toDateString()) }}" required class="rounded-lg border border-slate-300 px-3 py-2.5">
                </label>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold">Address and contacts</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="flex flex-col gap-2 text-sm font-medium md:col-span-2">Registered address
                    <textarea name="registered_address" required class="min-h-24 rounded-lg border border-slate-300 px-3 py-2.5">{{ old('registered_address', $profile?->registered_address) }}</textarea>
                </label>
                @foreach (['email' => 'Email', 'phone' => 'Phone', 'website' => 'Website'] as $field => $label)
                    <label class="flex flex-col gap-2 text-sm font-medium">{{ $label }}
                        <input name="{{ $field }}" value="{{ old($field, $profile?->{$field}) }}" class="rounded-lg border border-slate-300 px-3 py-2.5">
                    </label>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold">Regional settings and branding</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="flex flex-col gap-2 text-sm font-medium">Currency<input name="default_currency" value="{{ old('default_currency', $profile?->default_currency ?? 'PHP') }}" required class="rounded-lg border border-slate-300 px-3 py-2.5"></label>
                <label class="flex flex-col gap-2 text-sm font-medium">Timezone<input name="timezone" value="{{ old('timezone', $profile?->timezone ?? 'Asia/Manila') }}" required class="rounded-lg border border-slate-300 px-3 py-2.5"></label>
                <label class="flex flex-col gap-2 text-sm font-medium">Fiscal year start month<input type="number" min="1" max="12" name="fiscal_year_start_month" value="{{ old('fiscal_year_start_month', $profile?->fiscal_year_start_month ?? 1) }}" required class="rounded-lg border border-slate-300 px-3 py-2.5"></label>
                <label class="flex flex-col gap-2 text-sm font-medium">Logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="rounded-lg border border-slate-300 px-3 py-2.5">@error('logo')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
                <input type="hidden" name="active" value="1">
            </div>
        </section>

        <div><button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Save business profile</button></div>
    </form>
</x-app-layout>
