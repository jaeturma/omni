<?php

namespace App\Http\Controllers;

use App\Actions\ManageInventoryAdjustment;
use App\Enums\InventoryAdjustmentStatus;
use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Requests\TransitionInventoryAdjustmentRequest;
use App\Models\FiscalPeriod;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentReason;
use App\Models\ProductService;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryAdjustmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', InventoryAdjustment::class);

        return view('inventory-adjustments.index', ['adjustments' => InventoryAdjustment::query()
            ->with(['warehouse:id,code,name', 'reason:id,name'])->latest('adjustment_date')->latest('id')->paginate(25)]);
    }

    public function create(): View
    {
        Gate::authorize('create', InventoryAdjustment::class);

        return view('inventory-adjustments.create', [
            'warehouses' => Warehouse::query()->where('status', 'active')->orderBy('name')->get(),
            'periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'reasons' => InventoryAdjustmentReason::query()->where('active', true)->orderBy('name')->get(),
            'products' => ProductService::query()->with('unitOfMeasure:id,code')->where('type', 'product')
                ->where('is_inventory', true)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInventoryAdjustmentRequest $request, ManageInventoryAdjustment $manage): RedirectResponse
    {
        $adjustment = $manage->create($request->validated(), $request->user()->id);

        return redirect()->route('inventory-adjustments.show', $adjustment)->with('success', 'Inventory-adjustment draft saved.');
    }

    public function show(InventoryAdjustment $inventoryAdjustment): View
    {
        Gate::authorize('view', $inventoryAdjustment);

        return view('inventory-adjustments.show', ['adjustment' => $inventoryAdjustment
            ->load(['lines.product.unitOfMeasure', 'warehouse', 'fiscalPeriod', 'reason'])]);
    }

    public function transition(TransitionInventoryAdjustmentRequest $request, InventoryAdjustment $inventoryAdjustment, ManageInventoryAdjustment $manage): RedirectResponse
    {
        $manage->transition($inventoryAdjustment, InventoryAdjustmentStatus::from($request->validated('status')),
            $request->user()->id, $request->validated('reason'));

        return back()->with('success', 'Inventory-adjustment status updated.');
    }
}
