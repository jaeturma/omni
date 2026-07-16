@props(['action', 'method' => 'POST', 'customer' => null, 'submitLabel'])

<form method="POST" action="{{ $action }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="grid gap-4 md:grid-cols-2">
        <label class="flex flex-col gap-1 text-sm font-medium">Customer code<input name="code" value="{{ old('code', $customer?->code) }}" required maxlength="30" class="rounded-lg border border-slate-300 px-3 py-2">@error('code')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">Customer name<input name="name" value="{{ old('name', $customer?->name) }}" required class="rounded-lg border border-slate-300 px-3 py-2">@error('name')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">Customer type<select name="type" class="rounded-lg border border-slate-300 px-3 py-2"><option value="private" @selected(old('type', $customer?->type) === 'private')>Private</option><option value="government" @selected(old('type', $customer?->type) === 'government')>Government agency</option></select>@error('type')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">TIN<input name="tin" value="{{ old('tin', $customer?->tin) }}" maxlength="20" placeholder="123-456-789-00000" class="rounded-lg border border-slate-300 px-3 py-2">@error('tin')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium md:col-span-2">Complete address<textarea name="address" required rows="3" class="rounded-lg border border-slate-300 px-3 py-2">{{ old('address', $customer?->address) }}</textarea>@error('address')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">Contact person<input name="contact_person" value="{{ old('contact_person', $customer?->contact_person) }}" class="rounded-lg border border-slate-300 px-3 py-2"></label>
        <label class="flex flex-col gap-1 text-sm font-medium">Email<input type="email" name="email" value="{{ old('email', $customer?->email) }}" class="rounded-lg border border-slate-300 px-3 py-2">@error('email')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">Phone<input name="phone" value="{{ old('phone', $customer?->phone) }}" maxlength="30" class="rounded-lg border border-slate-300 px-3 py-2"></label>
        <label class="flex flex-col gap-1 text-sm font-medium">Payment terms (days)<input type="number" min="0" max="3650" name="payment_terms" value="{{ old('payment_terms', $customer?->payment_terms ?? 0) }}" required class="rounded-lg border border-slate-300 px-3 py-2">@error('payment_terms')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">Status<select name="status" class="rounded-lg border border-slate-300 px-3 py-2"><option value="active" @selected(old('status', $customer?->status ?? 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $customer?->status) === 'inactive')>Inactive</option></select></label>
    </div>
    <div class="mt-5 flex flex-wrap gap-3"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">{{ $submitLabel }}</button><a href="{{ route('customers.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</a></div>
</form>
