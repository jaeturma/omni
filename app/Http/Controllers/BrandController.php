<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Brand::class);
        $brands = Brand::query()
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$request->string('search').'%')->orWhere('name', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('brands.index', ['brands' => $brands]);
    }

    public function create(): View
    {
        Gate::authorize('create', Brand::class);

        return view('brands.create');
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        Brand::query()->create($request->validated() + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('brands.index')->with('success', 'Brand created.');
    }

    public function edit(Brand $brand): View
    {
        Gate::authorize('update', $brand);

        return view('brands.edit', ['brand' => $brand]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('brands.index')->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        Gate::authorize('delete', $brand);
        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Brand deleted.');
    }
}
