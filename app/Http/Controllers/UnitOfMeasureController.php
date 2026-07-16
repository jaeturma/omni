<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitOfMeasureRequest;
use App\Http\Requests\UpdateUnitOfMeasureRequest;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UnitOfMeasureController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', UnitOfMeasure::class);
        $unitsOfMeasure = UnitOfMeasure::query()
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$request->string('search').'%')->orWhere('name', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('units-of-measure.index', ['unitsOfMeasure' => $unitsOfMeasure]);
    }

    public function create(): View
    {
        Gate::authorize('create', UnitOfMeasure::class);

        return view('units-of-measure.create');
    }

    public function store(StoreUnitOfMeasureRequest $request): RedirectResponse
    {
        UnitOfMeasure::query()->create($request->validated() + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('units-of-measure.index')->with('success', 'Unit of measure created.');
    }

    public function edit(UnitOfMeasure $unitOfMeasure): View
    {
        Gate::authorize('update', $unitOfMeasure);

        return view('units-of-measure.edit', ['unitOfMeasure' => $unitOfMeasure]);
    }

    public function update(UpdateUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure): RedirectResponse
    {
        $unitOfMeasure->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('units-of-measure.index')->with('success', 'Unit of measure updated.');
    }

    public function destroy(UnitOfMeasure $unitOfMeasure): RedirectResponse
    {
        Gate::authorize('delete', $unitOfMeasure);
        $unitOfMeasure->delete();

        return redirect()->route('units-of-measure.index')->with('success', 'Unit of measure deleted.');
    }
}
