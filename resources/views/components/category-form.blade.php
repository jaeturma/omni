@props(['action', 'method' => 'POST', 'category' => null, 'parentCategories', 'submitLabel'])

<form method="POST" action="{{ $action }}" class="max-w-4xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="grid gap-4 md:grid-cols-2">
        <label class="flex flex-col gap-1 text-sm font-medium">Category code<input name="code" value="{{ old('code', $category?->code) }}" required maxlength="30" class="rounded-lg border border-slate-300 px-3 py-2">@error('code')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">Category name<input name="name" value="{{ old('name', $category?->name) }}" required class="rounded-lg border border-slate-300 px-3 py-2">@error('name')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">Category type<select name="type" class="rounded-lg border border-slate-300 px-3 py-2"><option value="product" @selected(old('type', $category?->type ?? 'product') === 'product')>Product</option><option value="service" @selected(old('type', $category?->type) === 'service')>Service</option></select>@error('type')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">Parent category<select name="parent_id" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">None</option>@foreach ($parentCategories as $parentCategory)<option value="{{ $parentCategory->id }}" @selected((string) old('parent_id', $category?->parent_id) === (string) $parentCategory->id)>{{ ucfirst($parentCategory->type) }} — {{ $parentCategory->name }}</option>@endforeach</select>@error('parent_id')<span class="text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex flex-col gap-1 text-sm font-medium">Status<select name="status" class="rounded-lg border border-slate-300 px-3 py-2"><option value="active" @selected(old('status', $category?->status ?? 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $category?->status) === 'inactive')>Inactive</option></select>@error('status')<span class="text-red-700">{{ $message }}</span>@enderror</label>
    </div>
    <p class="mt-4 text-sm text-slate-500">The selected parent must have the same category type.</p>
    <div class="mt-5 flex flex-wrap gap-3"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">{{ $submitLabel }}</button><a href="{{ route('categories.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</a></div>
</form>
