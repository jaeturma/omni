<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Category::class);
        $categories = Category::query()->with('parent:id,name')
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$request->string('search').'%')->orWhere('name', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('type')->orderBy('name')->paginate(25)->withQueryString();

        return view('categories.index', ['categories' => $categories]);
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('categories.create', ['parentCategories' => $this->parentCategories()]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated() + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('categories.edit', ['category' => $category, 'parentCategories' => $this->parentCategories($category)]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);
        if ($category->children()->exists()) {
            return back()->with('error', 'A category with child categories cannot be deleted.');
        }
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }

    private function parentCategories(?Category $excluded = null)
    {
        return Category::query()->when($excluded, fn ($query) => $query->whereKeyNot($excluded->getKey()))
            ->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);
    }
}
