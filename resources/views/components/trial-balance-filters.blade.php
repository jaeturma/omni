@props(['action', 'filters', 'periods', 'accounts', 'trialBalance' => false])
<form method="GET" action="{{ $action }}" class="grid gap-4 rounded-xl bg-white p-4 ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-4">
    <label class="grid gap-1 text-sm">Fiscal period
        <select name="fiscal_period_id" class="rounded-lg border-slate-300"><option value="">Custom range</option>
            @foreach($periods as $period)<option value="{{ $period->id }}" @selected(($filters['fiscal_period_id'] ?? null) == $period->id)>{{ $period->fiscalYear->name }} — {{ $period->name }}</option>@endforeach
        </select>
    </label>
    <label class="grid gap-1 text-sm">Start date
        <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="rounded-lg border-slate-300">
        @error('start_date')<span class="text-red-600">{{ $message }}</span>@enderror
    </label>
    <label class="grid gap-1 text-sm">End date
        <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="rounded-lg border-slate-300">
        @error('end_date')<span class="text-red-600">{{ $message }}</span>@enderror
    </label>
    <label class="grid gap-1 text-sm">As of
        <input type="date" name="as_of" value="{{ $filters['as_of'] }}" class="rounded-lg border-slate-300">
        @error('as_of')<span class="text-red-600">{{ $message }}</span>@enderror
    </label>
    @if($trialBalance)
        <label class="grid gap-1 text-sm">Basis
            <select name="basis" class="rounded-lg border-slate-300"><option value="unadjusted" @selected($filters['basis'] === 'unadjusted')>Unadjusted</option><option value="adjusted" @selected($filters['basis'] === 'adjusted')>Adjusted</option></select>
        </label>
        <label class="grid gap-1 text-sm">Account hierarchy
            <select name="account_id" class="rounded-lg border-slate-300"><option value="">All accounts</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(($filters['account_id'] ?? null) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>@endforeach</select>
        </label>
        <label class="grid gap-1 text-sm">Detail
            <select name="detail" class="rounded-lg border-slate-300"><option value="postable" @selected($filters['detail'] === 'postable')>Postable accounts</option><option value="hierarchy" @selected($filters['detail'] === 'hierarchy')>Account hierarchy</option></select>
        </label>
    @else
        <input type="hidden" name="basis" value="{{ $filters['basis'] }}">
        <input type="hidden" name="detail" value="{{ $filters['detail'] }}">
    @endif
    <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Apply filters</button></div>
</form>
