<x-app-layout title="Create Petty-Cash Fund">
    <x-page-header title="Create Petty-Cash Fund" description="Assign one active petty-cash account to an accountable custodian." />
    <form method="POST" action="{{ route('petty-cash.funds.store') }}" class="grid gap-5 rounded-2xl bg-white p-6 ring-1 ring-slate-200 md:grid-cols-2">
        @csrf
        <label class="grid gap-1 text-sm">Dedicated account<select name="financial_account_id" required class="rounded-lg border-slate-300"><option value="">Select account</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('financial_account_id') == $account->id)>{{ $account->code }} — {{ $account->name }}</option>@endforeach</select>@error('financial_account_id')<span class="text-red-600">{{ $message }}</span>@enderror</label>
        <label class="grid gap-1 text-sm">Custodian<select name="custodian_id" required class="rounded-lg border-slate-300"><option value="">Select custodian</option>@foreach($custodians as $custodian)<option value="{{ $custodian->id }}" @selected(old('custodian_id') == $custodian->id)>{{ $custodian->name }}</option>@endforeach</select>@error('custodian_id')<span class="text-red-600">{{ $message }}</span>@enderror</label>
        <label class="grid gap-1 text-sm">Approved fund limit<input type="number" step="0.0001" min="0.0001" name="approved_fund_limit" value="{{ old('approved_fund_limit') }}" required class="rounded-lg border-slate-300">@error('approved_fund_limit')<span class="text-red-600">{{ $message }}</span>@enderror</label>
        <button class="w-fit rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Create fund</button>
    </form>
</x-app-layout>
