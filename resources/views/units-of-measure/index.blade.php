<x-app-layout title="Units of Measure">
    <x-page-header title="Units of Measure" description="Maintain reusable quantity labels such as pcs, box, ream, unit, set, pack, and lot." />
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('units-of-measure.index') }}" class="flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1 text-sm font-medium">Search<input name="search" value="{{ request('search') }}" placeholder="Code or name" class="rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="flex flex-col gap-1 text-sm font-medium">Status<select name="status" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">All</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></label>
            <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Filter</button>
        </form>
        @can('create', \App\Models\UnitOfMeasure::class)<a href="{{ route('units-of-measure.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Create unit</a>@endcan
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-6 py-3">Code</th><th class="px-6 py-3">Unit name</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
    @forelse ($unitsOfMeasure as $unitOfMeasure)<tr><td class="px-6 py-4 font-mono font-medium">{{ $unitOfMeasure->code }}</td><td class="px-6 py-4 font-medium">{{ $unitOfMeasure->name }}</td><td class="px-6 py-4 capitalize">{{ $unitOfMeasure->status }}</td><td class="px-6 py-4">@can('update', $unitOfMeasure)<a href="{{ route('units-of-measure.edit', $unitOfMeasure) }}" class="font-semibold text-blue-700">Edit</a>@endcan</td></tr>@empty<tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No units of measure found.</td></tr>@endforelse
    </tbody></table></div><div class="mt-6">{{ $unitsOfMeasure->links() }}</div>
</x-app-layout>
