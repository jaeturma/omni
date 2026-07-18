@props(['filters', 'suppliers', 'action'])
<form method="GET" action="{{ $action }}" class="mb-5 grid gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200 md:grid-cols-5">
    <label class="text-sm">As of<input class="mt-1 w-full rounded-lg border-slate-300" type="date" name="as_of" value="{{ $filters['as_of'] }}"></label>
    <label class="text-sm">Supplier<select class="mt-1 w-full rounded-lg border-slate-300" name="supplier_id"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>
    <label class="text-sm">State<select class="mt-1 w-full rounded-lg border-slate-300" name="state"><option value="">All open</option>@foreach(['partial' => 'Partially paid', 'overdue' => 'Overdue'] as $value => $label)<option value="{{ $value }}" @selected(($filters['state'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="text-sm">Bucket<select class="mt-1 w-full rounded-lg border-slate-300" name="bucket"><option value="">All buckets</option>@foreach(\App\Reports\AccountsPayableReport::BUCKETS as $bucket)<option value="{{ $bucket }}" @selected(($filters['bucket'] ?? '') === $bucket)>{{ $bucket }}</option>@endforeach</select></label>
    <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Apply filters</button></div>
</form>
