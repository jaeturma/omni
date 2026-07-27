@props(['action', 'filters', 'accounts', 'sourceTypes', 'customers', 'suppliers', 'financialAccounts', 'products', 'warehouses', 'requireAccount' => false])
<form method="GET" action="{{ $action }}" class="grid gap-4 rounded-xl bg-white p-4 ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-4">
    <label class="grid gap-1 text-sm">Start date
        <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="rounded-lg border-slate-300">
        @error('start_date')<span class="text-red-600">{{ $message }}</span>@enderror
    </label>
    <label class="grid gap-1 text-sm">End date
        <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="rounded-lg border-slate-300">
        @error('end_date')<span class="text-red-600">{{ $message }}</span>@enderror
    </label>
    <label class="grid gap-1 text-sm">Account
        <select name="account_id" class="rounded-lg border-slate-300">
            @unless($requireAccount)<option value="">All accounts</option>@endunless
            @foreach($accounts as $account)<option value="{{ $account->id }}" @selected(($filters['account_id'] ?? null) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>@endforeach
        </select>
        @error('account_id')<span class="text-red-600">{{ $message }}</span>@enderror
    </label>
    <label class="grid gap-1 text-sm">Source type
        <select name="source_type" class="rounded-lg border-slate-300"><option value="">All sources</option>
            @foreach($sourceTypes as $type)<option value="{{ $type->value }}" @selected(($filters['source_type'] ?? null) === $type->value)>{{ str($type->value)->headline() }}</option>@endforeach
        </select>
    </label>
    <label class="grid gap-1 text-sm">Reference
        <input name="reference" value="{{ $filters['reference'] ?? '' }}" class="rounded-lg border-slate-300" placeholder="Journal or source reference">
    </label>
    <label class="grid gap-1 text-sm">Customer
        <select name="customer_id" class="rounded-lg border-slate-300"><option value="">All</option>@foreach($customers as $item)<option value="{{ $item->id }}" @selected(($filters['customer_id'] ?? null) == $item->id)>{{ $item->name }}</option>@endforeach</select>
    </label>
    <label class="grid gap-1 text-sm">Supplier
        <select name="supplier_id" class="rounded-lg border-slate-300"><option value="">All</option>@foreach($suppliers as $item)<option value="{{ $item->id }}" @selected(($filters['supplier_id'] ?? null) == $item->id)>{{ $item->name }}</option>@endforeach</select>
    </label>
    <label class="grid gap-1 text-sm">Financial account
        <select name="financial_account_id" class="rounded-lg border-slate-300"><option value="">All</option>@foreach($financialAccounts as $item)<option value="{{ $item->id }}" @selected(($filters['financial_account_id'] ?? null) == $item->id)>{{ $item->code }} — {{ $item->name }}</option>@endforeach</select>
    </label>
    <label class="grid gap-1 text-sm">Product
        <select name="product_id" class="rounded-lg border-slate-300"><option value="">All</option>@foreach($products as $item)<option value="{{ $item->id }}" @selected(($filters['product_id'] ?? null) == $item->id)>{{ $item->sku }} — {{ $item->name }}</option>@endforeach</select>
    </label>
    <label class="grid gap-1 text-sm">Warehouse
        <select name="warehouse_id" class="rounded-lg border-slate-300"><option value="">All</option>@foreach($warehouses as $item)<option value="{{ $item->id }}" @selected(($filters['warehouse_id'] ?? null) == $item->id)>{{ $item->code }} — {{ $item->name }}</option>@endforeach</select>
    </label>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="include_descendants" value="1" @checked($filters['include_descendants'] ?? false) class="rounded border-slate-300">
        Include account descendants
    </label>
    <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Apply filters</button></div>
</form>
