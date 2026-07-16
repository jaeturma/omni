<x-app-layout title="Suppliers">
    <x-page-header title="Suppliers" description="Maintain supplier identity, contact, and payment terms." />
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('suppliers.index') }}" class="flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1 text-sm font-medium">Search<input name="search" value="{{ request('search') }}" placeholder="Code, name, or TIN" class="rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="flex flex-col gap-1 text-sm font-medium">Status<select name="status" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">All</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></label>
            <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Filter</button>
        </form>
        @can('create', \App\Models\Supplier::class)<a href="{{ route('suppliers.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Create supplier</a>@endcan
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-6 py-3">Code</th><th class="px-6 py-3">Supplier</th><th class="px-6 py-3">Contact</th><th class="px-6 py-3">Terms</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
    @forelse ($suppliers as $supplier)<tr><td class="px-6 py-4 font-mono font-medium">{{ $supplier->code }}</td><td class="px-6 py-4"><p class="font-medium">{{ $supplier->name }}</p><p class="text-xs text-slate-500">{{ $supplier->tin ?: 'No TIN' }}</p></td><td class="px-6 py-4"><p>{{ $supplier->contact_person }}</p><p class="text-xs text-slate-500">{{ $supplier->email }}</p></td><td class="px-6 py-4">{{ $supplier->payment_terms }} days</td><td class="px-6 py-4 capitalize">{{ $supplier->status }}</td><td class="px-6 py-4">@can('update', $supplier)<a href="{{ route('suppliers.edit', $supplier) }}" class="font-semibold text-blue-700">Edit</a>@endcan</td></tr>@empty<tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">No suppliers found.</td></tr>@endforelse
    </tbody></table></div><div class="mt-6">{{ $suppliers->links() }}</div>
</x-app-layout>
