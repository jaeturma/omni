<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductServiceRequest;
use App\Http\Requests\UpdateProductServiceRequest;
use App\Models\Category;
use App\Models\ProductService;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductServiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProductService::class);
        $productServices = ProductService::query()->with(['category:id,name', 'unitOfMeasure:id,code'])
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('sku', 'like', '%'.$request->string('search').'%')->orWhere('barcode', 'like', '%'.$request->string('search').'%')->orWhere('name', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('products-services.index', ['productServices' => $productServices]);
    }

    public function create(): View
    {
        Gate::authorize('create', ProductService::class);

        return view('products-services.create', $this->formOptions());
    }

    public function store(StoreProductServiceRequest $request): RedirectResponse
    {
        ProductService::query()->create($request->validated() + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('products-services.index')->with('success', 'Catalog item created.');
    }

    public function edit(ProductService $productService): View
    {
        Gate::authorize('update', $productService);

        return view('products-services.edit', ['productService' => $productService] + $this->formOptions());
    }

    public function update(UpdateProductServiceRequest $request, ProductService $productService): RedirectResponse
    {
        $productService->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('products-services.index')->with('success', 'Catalog item updated.');
    }

    public function destroy(ProductService $productService): RedirectResponse
    {
        Gate::authorize('delete', $productService);
        $productService->delete();

        return redirect()->route('products-services.index')->with('success', 'Catalog item deleted.');
    }

    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']),
            'unitsOfMeasure' => UnitOfMeasure::query()->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }
}
