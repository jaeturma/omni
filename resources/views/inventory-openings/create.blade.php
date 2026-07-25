<x-app-layout title="New Inventory Opening Balance">
    <x-page-header title="New Inventory Opening Balance" description="Enter starting stock for one warehouse. Totals are calculated on the server." />
    <form method="POST" action="{{ route('inventory-opening-balances.store') }}" class="space-y-6" x-data="{ lines: [{}] }">
        @csrf
        <div class="grid gap-5 rounded-2xl bg-white p-6 ring-1 ring-slate-200 md:grid-cols-2">
            <div><x-input-label for="opening_date" value="Opening date" /><x-text-input id="opening_date" name="opening_date" type="date" class="mt-1 block w-full" :value="old('opening_date')" required /><x-input-error :messages="$errors->get('opening_date')" class="mt-2" /></div>
            <div><x-input-label for="fiscal_period_id" value="Fiscal period" /><select id="fiscal_period_id" name="fiscal_period_id" class="mt-1 block w-full rounded-lg border-slate-300" required><option value="">Select period</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(old('fiscal_period_id') == $period->id)>{{ $period->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('fiscal_period_id')" class="mt-2" /></div>
            <div><x-input-label for="warehouse_id" value="Warehouse" /><select id="warehouse_id" name="warehouse_id" class="mt-1 block w-full rounded-lg border-slate-300" required><option value="">Select warehouse</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" /></div>
            <div><x-input-label for="reference" value="Reference" /><x-text-input id="reference" name="reference" class="mt-1 block w-full" :value="old('reference')" /><x-input-error :messages="$errors->get('reference')" class="mt-2" /></div>
            <div class="md:col-span-2"><x-input-label for="notes" value="Notes" /><textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-lg border-slate-300">{{ old('notes') }}</textarea><x-input-error :messages="$errors->get('notes')" class="mt-2" /></div>
        </div>
        <div class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
            <div class="flex items-center justify-between gap-4"><h2 class="font-semibold">Opening stock lines</h2><button type="button" @click="lines.push({})" class="rounded-lg border border-blue-700 px-3 py-2 text-sm font-semibold text-blue-700">Add line</button></div>
            <div class="mt-4 space-y-4">
                <template x-for="(line, index) in lines" :key="index">
                    <div class="grid gap-4 rounded-xl border border-slate-200 p-4 md:grid-cols-12">
                        <div class="md:col-span-6"><label class="text-sm font-medium">Inventory product</label><select :name="`lines[${index}][product_service_id]`" class="mt-1 block w-full rounded-lg border-slate-300" required><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }} ({{ $product->unitOfMeasure->code }})</option>@endforeach</select></div>
                        <div class="md:col-span-2"><label class="text-sm font-medium">Quantity</label><input :name="`lines[${index}][quantity]`" type="number" min="0.0001" step="0.0001" class="mt-1 block w-full rounded-lg border-slate-300" required></div>
                        <div class="md:col-span-3"><label class="text-sm font-medium">Unit cost</label><input :name="`lines[${index}][unit_cost]`" type="number" min="0" step="0.0001" class="mt-1 block w-full rounded-lg border-slate-300" required></div>
                        <div class="flex items-end md:col-span-1"><button type="button" @click="lines.splice(index, 1)" x-show="lines.length > 1" class="rounded-lg px-2 py-2 text-sm font-semibold text-red-700">Remove</button></div>
                    </div>
                </template>
            </div>
            <x-input-error :messages="$errors->get('lines')" class="mt-3" />
            @foreach($errors->getMessages() as $key => $messages) @if(str_starts_with($key, 'lines.'))<x-input-error :messages="$messages" class="mt-2" />@endif @endforeach
        </div>
        <div class="flex justify-end gap-3"><a href="{{ route('inventory-opening-balances.index') }}" class="rounded-lg border border-slate-300 px-4 py-2">Cancel</a><button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Save draft</button></div>
    </form>
</x-app-layout>
