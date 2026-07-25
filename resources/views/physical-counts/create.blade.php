<x-app-layout title="New Physical Count">
    <x-page-header title="New Physical Count" description="The system quantity and cost snapshots are frozen when this session is saved." />
    <form method="POST" action="{{ route('physical-counts.store') }}" class="grid gap-6">@csrf
        <div class="grid gap-5 rounded-2xl bg-white p-6 ring-1 ring-slate-200 md:grid-cols-2">
            <div><x-input-label for="count_date" value="Count date" /><x-text-input id="count_date" name="count_date" type="date" class="mt-1 block w-full" :value="old('count_date', now()->toDateString())" required /><x-input-error :messages="$errors->get('count_date')" class="mt-2" /></div>
            <div><x-input-label for="fiscal_period_id" value="Fiscal period" /><select id="fiscal_period_id" name="fiscal_period_id" required class="mt-1 block w-full rounded-lg border-slate-300"><option value="">Select period</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(old('fiscal_period_id') == $period->id)>{{ $period->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('fiscal_period_id')" class="mt-2" /></div>
            <div><x-input-label for="warehouse_id" value="Warehouse" /><select id="warehouse_id" name="warehouse_id" required class="mt-1 block w-full rounded-lg border-slate-300"><option value="">Select warehouse</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" /></div>
            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4"><input type="hidden" name="blind_count" value="0"><input type="checkbox" name="blind_count" value="1" @checked(old('blind_count')) class="rounded border-slate-300"><span><span class="block font-medium">Blind count</span><span class="text-sm text-slate-500">Hide system quantities and costs while counting.</span></span></label>
            <div class="md:col-span-2"><x-input-label for="notes" value="Notes" /><textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-lg border-slate-300">{{ old('notes') }}</textarea><x-input-error :messages="$errors->get('notes')" class="mt-2" /></div>
        </div>
        <div class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
            <h2 class="font-semibold">Products to count</h2>
            <p class="mt-1 text-sm text-slate-500">Select every inventory product included in this count session.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach($products as $product)
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4"><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, old('product_ids', []))) class="mt-1 rounded border-slate-300"><span><span class="block font-medium">{{ $product->sku }} — {{ $product->name }}</span><span class="text-sm text-slate-500">{{ $product->unitOfMeasure->code }}</span></span></label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('product_ids')" class="mt-3" />
            @foreach($errors->getMessages() as $key => $messages)@if(str_starts_with($key, 'product_ids.'))<x-input-error :messages="$messages" class="mt-2" />@endif @endforeach
        </div>
        <div class="flex justify-end gap-3"><a href="{{ route('physical-counts.index') }}" class="rounded-lg border border-slate-300 px-4 py-2">Cancel</a><button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Freeze snapshot</button></div>
    </form>
</x-app-layout>
