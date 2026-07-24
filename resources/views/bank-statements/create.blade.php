<x-app-layout title="Import Bank Statement">
    <x-page-header title="Import Bank Statement" description="Upload a CSV and map its columns. The imported rows remain staging data only." />
    <form method="POST" action="{{ route('bank-statements.store') }}" enctype="multipart/form-data" class="grid gap-5 rounded-2xl bg-white p-6 ring-1 ring-slate-200 md:grid-cols-2">
        @csrf
        <label class="grid gap-1 text-sm font-medium">Financial account
            <select name="financial_account_id" required class="rounded-lg border-slate-300">
                <option value="">Select account</option>
                @foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('financial_account_id') == $account->id)>{{ $account->code }} — {{ $account->name }}</option>@endforeach
            </select>
            @error('financial_account_id')<span class="text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="grid gap-1 text-sm font-medium">CSV file
            <input type="file" name="statement_file" accept=".csv,text/csv" required class="rounded-lg border border-slate-300 p-2">
            @error('statement_file')<span class="text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="grid gap-1 text-sm font-medium">Statement start<input type="date" name="statement_start_date" value="{{ old('statement_start_date') }}" required class="rounded-lg border-slate-300">@error('statement_start_date')<span class="text-red-600">{{ $message }}</span>@enderror</label>
        <label class="grid gap-1 text-sm font-medium">Statement end<input type="date" name="statement_end_date" value="{{ old('statement_end_date') }}" required class="rounded-lg border-slate-300">@error('statement_end_date')<span class="text-red-600">{{ $message }}</span>@enderror</label>
        <label class="grid gap-1 text-sm font-medium">Date format
            <select name="date_format" class="rounded-lg border-slate-300"><option value="Y-m-d">YYYY-MM-DD</option><option value="m/d/Y">MM/DD/YYYY</option><option value="d/m/Y">DD/MM/YYYY</option><option value="m/d/y">MM/DD/YY</option><option value="d-m-Y">DD-MM-YYYY</option></select>
        </label>
        <div></div>
        @foreach(['transaction_date' => 'Transaction date', 'posting_date' => 'Posting date (optional; defaults to transaction date)', 'description' => 'Description', 'reference_number' => 'Reference number (optional)', 'debit' => 'Debit', 'credit' => 'Credit', 'running_balance' => 'Running balance (optional)'] as $field => $label)
            <label class="grid gap-1 text-sm font-medium">{{ $label }}
                <input name="{{ $field }}_column" value="{{ old($field.'_column') }}" @required(in_array($field, ['transaction_date', 'description', 'debit', 'credit'])) placeholder="Exact CSV header" class="rounded-lg border-slate-300">
                @error($field.'_column')<span class="text-red-600">{{ $message }}</span>@enderror
            </label>
        @endforeach
        <div class="md:col-span-2"><button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Import statement</button></div>
    </form>
</x-app-layout>
