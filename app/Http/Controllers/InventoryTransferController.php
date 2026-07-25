<?php

namespace App\Http\Controllers;

use App\Actions\ManageInventoryTransfer;
use App\Enums\InventoryTransferStatus;
use App\Http\Requests\StoreInventoryTransferRequest;
use App\Http\Requests\TransitionInventoryTransferRequest;
use App\Models\FiscalPeriod;
use App\Models\InventoryTransfer;
use App\Models\ProductService;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryTransferController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', InventoryTransfer::class);

        return view('inventory-transfers.index', ['transfers' => InventoryTransfer::query()
            ->with(['sourceWarehouse:id,code,name', 'destinationWarehouse:id,code,name'])
            ->latest('transfer_date')->latest('id')->paginate(25)]);
    }

    public function create(): View
    {
        Gate::authorize('create', InventoryTransfer::class);

        return view('inventory-transfers.create', [
            'warehouses' => Warehouse::query()->where('status', 'active')->orderBy('name')->get(),
            'periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'products' => ProductService::query()->with('unitOfMeasure:id,code')->where('type', 'product')
                ->where('is_inventory', true)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInventoryTransferRequest $request, ManageInventoryTransfer $manage): RedirectResponse
    {
        $transfer = $manage->create($request->validated(), $request->user()->id);

        return redirect()->route('inventory-transfers.show', $transfer)->with('success', 'Inventory-transfer draft saved.');
    }

    public function show(InventoryTransfer $inventoryTransfer): View
    {
        Gate::authorize('view', $inventoryTransfer);

        return view('inventory-transfers.show', ['transfer' => $inventoryTransfer
            ->load(['lines.product.unitOfMeasure', 'sourceWarehouse', 'destinationWarehouse', 'fiscalPeriod'])]);
    }

    public function transition(TransitionInventoryTransferRequest $request, InventoryTransfer $inventoryTransfer, ManageInventoryTransfer $manage): RedirectResponse
    {
        $manage->transition($inventoryTransfer, InventoryTransferStatus::from($request->validated('status')),
            $request->user()->id, $request->validated('reason'));

        return back()->with('success', 'Inventory-transfer status updated.');
    }
}
