<x-app-layout title="Start Bank Reconciliation">
    <x-page-header title="Start Bank Reconciliation" description="Select one active statement import and enter its reported balances." />
    <form method="POST" action="{{ route('bank-reconciliations.store') }}" class="grid gap-5 rounded-2xl bg-white p-6 ring-1 ring-slate-200 md:grid-cols-2">
        @csrf
        <label class="grid gap-1 text-sm font-medium md:col-span-2">Statement import<select name="bank_statement_import_id" required class="rounded-lg border-slate-300"><option value="">Select statement</option>@foreach($imports as $import)<option value="{{ $import->id }}">{{ $import->financialAccount->code }} — {{ $import->source_filename }} ({{ $import->statement_start_date->toDateString() }} to {{ $import->statement_end_date->toDateString() }})</option>@endforeach</select>@error('bank_statement_import_id')<span class="text-red-600">{{ $message }}</span>@enderror</label>
        <label class="grid gap-1 text-sm font-medium">Statement opening balance<input name="statement_opening_balance" inputmode="decimal" required class="rounded-lg border-slate-300">@error('statement_opening_balance')<span class="text-red-600">{{ $message }}</span>@enderror</label>
        <label class="grid gap-1 text-sm font-medium">Statement closing balance<input name="statement_closing_balance" inputmode="decimal" required class="rounded-lg border-slate-300">@error('statement_closing_balance')<span class="text-red-600">{{ $message }}</span>@enderror</label>
        <div class="md:col-span-2"><button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Start reconciliation</button></div>
    </form>
</x-app-layout>
