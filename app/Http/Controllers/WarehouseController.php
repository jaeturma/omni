<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Warehouse::class);
        $warehouses = Warehouse::query()
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$request->string('search').'%')->orWhere('name', 'like', '%'.$request->string('search').'%')->orWhere('address', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('warehouses.index', ['warehouses' => $warehouses]);
    }

    public function create(): View
    {
        Gate::authorize('create', Warehouse::class);

        return view('warehouses.create');
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        Warehouse::query()->create($request->validated() + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse created.');
    }

    public function edit(Warehouse $warehouse): View
    {
        Gate::authorize('update', $warehouse);

        return view('warehouses.edit', ['warehouse' => $warehouse]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse updated.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('delete', $warehouse);
        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', 'Warehouse deleted.');
    }
}
