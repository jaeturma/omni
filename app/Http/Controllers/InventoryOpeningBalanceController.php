<?php

namespace App\Http\Controllers;

use App\Actions\ManageInventoryOpeningBalance;
use App\Enums\InventoryOpeningStatus;
use App\Http\Requests\StoreInventoryOpeningBalanceRequest;
use App\Http\Requests\TransitionInventoryOpeningBalanceRequest;
use App\Models\FiscalPeriod;
use App\Models\InventoryOpeningBalance;
use App\Models\ProductService;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryOpeningBalanceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', InventoryOpeningBalance::class);

        return view('inventory-openings.index', ['openings' => InventoryOpeningBalance::query()
            ->with(['warehouse:id,code,name', 'fiscalPeriod:id,name'])->latest('opening_date')->latest('id')->paginate(25)]);
    }

    public function create(): View
    {
        Gate::authorize('create', InventoryOpeningBalance::class);

        return view('inventory-openings.create', [
            'warehouses' => Warehouse::query()->where('status', 'active')->orderBy('name')->get(),
            'periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'products' => ProductService::query()->with('unitOfMeasure:id,code')->where('type', 'product')
                ->where('is_inventory', true)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInventoryOpeningBalanceRequest $request, ManageInventoryOpeningBalance $manage): RedirectResponse
    {
        $opening = $manage->create($request->validated(), $request->user()->id);

        return redirect()->route('inventory-opening-balances.show', $opening)->with('success', 'Opening-balance draft saved.');
    }

    public function show(InventoryOpeningBalance $inventoryOpeningBalance): View
    {
        Gate::authorize('view', $inventoryOpeningBalance);

        return view('inventory-openings.show', ['opening' => $inventoryOpeningBalance->load(['lines.product.unitOfMeasure', 'warehouse', 'fiscalPeriod'])]);
    }

    public function transition(TransitionInventoryOpeningBalanceRequest $request, InventoryOpeningBalance $inventoryOpeningBalance, ManageInventoryOpeningBalance $manage): RedirectResponse
    {
        $manage->transition($inventoryOpeningBalance, InventoryOpeningStatus::from($request->validated('status')),
            $request->user()->id, $request->validated('reason'));

        return back()->with('success', 'Opening-balance status updated.');
    }
}
