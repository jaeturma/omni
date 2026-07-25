<?php

namespace App\Http\Controllers;

use App\Actions\ManagePhysicalCount;
use App\Enums\PhysicalCountStatus;
use App\Http\Requests\RecordPhysicalCountRequest;
use App\Http\Requests\StorePhysicalCountRequest;
use App\Http\Requests\TransitionPhysicalCountRequest;
use App\Models\FiscalPeriod;
use App\Models\PhysicalCount;
use App\Models\ProductService;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PhysicalCountController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PhysicalCount::class);

        return view('physical-counts.index', ['counts' => PhysicalCount::query()
            ->with('warehouse:id,code,name')->latest('count_date')->latest('id')->paginate(25)]);
    }

    public function create(): View
    {
        Gate::authorize('create', PhysicalCount::class);

        return view('physical-counts.create', [
            'warehouses' => Warehouse::query()->where('status', 'active')->orderBy('name')->get(),
            'periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'products' => ProductService::query()->with('unitOfMeasure:id,code')->where('type', 'product')
                ->where('is_inventory', true)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StorePhysicalCountRequest $request, ManagePhysicalCount $manage): RedirectResponse
    {
        $count = $manage->create($request->validated(), $request->user()->id);

        return redirect()->route('physical-counts.show', $count)->with('success', 'Physical-count snapshot saved.');
    }

    public function show(PhysicalCount $physicalCount): View
    {
        Gate::authorize('view', $physicalCount);

        return view('physical-counts.show', ['count' => $physicalCount
            ->load(['lines.product.unitOfMeasure', 'warehouse', 'fiscalPeriod'])]);
    }

    public function record(RecordPhysicalCountRequest $request, PhysicalCount $physicalCount, ManagePhysicalCount $manage): RedirectResponse
    {
        $manage->record($physicalCount, $request->validated(), $request->user()->id);

        return back()->with('success', 'Counted quantities saved.');
    }

    public function review(Request $request, PhysicalCount $physicalCount, ManagePhysicalCount $manage): RedirectResponse
    {
        Gate::authorize('review', $physicalCount);
        $manage->review($physicalCount, $request->user()->id);

        return back()->with('success', 'Physical count reviewed.');
    }

    public function transition(TransitionPhysicalCountRequest $request, PhysicalCount $physicalCount, ManagePhysicalCount $manage): RedirectResponse
    {
        $manage->transition($physicalCount, PhysicalCountStatus::from($request->validated('status')),
            $request->user()->id, $request->validated('reason'));

        return back()->with('success', 'Physical-count status updated.');
    }
}
