<?php

namespace App\Reports;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryTransferStatus;
use App\Models\InventoryMovement;
use App\Models\InventoryTransferLine;
use App\Models\ProductService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class InventoryStockReport
{
    /** @param array<string, mixed> $filters */
    public function stockPaginator(array $filters): LengthAwarePaginator
    {
        $paginator = $this->stockQuery($filters)->paginate(50)->withQueryString();
        $stocks = (new InventoryMovement)->newCollection($paginator->items());
        $this->prepareStocks($stocks);
        $paginator->setCollection($stocks);

        return $paginator;
    }

    /** @param array<string, mixed> $filters
     * @return EloquentCollection<int, InventoryMovement>
     */
    public function stockRows(array $filters): EloquentCollection
    {
        $stocks = $this->stockQuery($filters)->get();
        $this->prepareStocks($stocks);

        return $stocks;
    }

    /** @param array<string, mixed> $filters */
    public function ledgerPaginator(array $filters): LengthAwarePaginator
    {
        $paginator = $this->movementQuery($filters)
            ->with($this->sourceRelations())
            ->latest('movement_date')->latest('id')->paginate(50)->withQueryString();
        $paginator->getCollection()->each(fn (InventoryMovement $movement) => $movement->setAttribute(
            'source_reference', $this->sourceReference($movement)
        ));

        return $paginator;
    }

    /** @param array<string, mixed> $filters */
    public function negativeStocks(array $filters): Collection
    {
        return $this->stockRows($filters)
            ->filter(fn (InventoryMovement $stock): bool => bccomp($stock->quantity, '0', 4) < 0)->values();
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters, bool $includeValuation): array
    {
        $openingQuantity = $movementQuantity = $openingValue = $movementValue = '0.0000';
        foreach ($this->movementQuery($filters, false)->cursor() as $movement) {
            if ($movement->movement_date->lt($filters['start_date'])) {
                $openingQuantity = bcadd($openingQuantity, $movement->quantity, 4);
                $openingValue = bcadd($openingValue, $movement->total_cost, 4);
            } elseif ($movement->movement_date->lte($filters['end_date'])) {
                $movementQuantity = bcadd($movementQuantity, $movement->quantity, 4);
                $movementValue = bcadd($movementValue, $movement->total_cost, 4);
            }
        }

        $stocks = $this->stockQuery($filters)->get();
        $quantity = $value = '0.0000';
        foreach ($stocks as $stock) {
            $quantity = bcadd($quantity, $stock->quantity, 4);
            $value = bcadd($value, bcmul($stock->quantity, $stock->as_of_average_cost ?? '0.0000', 4), 4);
        }

        return [
            'as_of_quantity' => $quantity,
            'as_of_value' => $includeValuation ? $value : null,
            'opening_quantity' => $openingQuantity,
            'movement_quantity' => $movementQuantity,
            'closing_quantity' => bcadd($openingQuantity, $movementQuantity, 4),
            'opening_value' => $includeValuation ? $openingValue : null,
            'movement_value' => $includeValuation ? $movementValue : null,
            'closing_value' => $includeValuation ? bcadd($openingValue, $movementValue, 4) : null,
        ];
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, ProductService>
     */
    public function alerts(array $filters): Collection
    {
        $totals = $this->stockQuery($filters)->get()->groupBy('product_service_id')
            ->map(fn (Collection $stocks): string => $stocks->reduce(
                fn (string $total, InventoryMovement $stock): string => bcadd($total, $stock->quantity, 4), '0.0000'
            ));

        return ProductService::query()->with(['category:id,name', 'brand:id,name'])
            ->where('type', 'product')->where('is_inventory', true)
            ->when(filled($filters['product_service_id']), fn (Builder $query) => $query->whereKey($filters['product_service_id']))
            ->when(filled($filters['category_id']), fn (Builder $query) => $query->where('category_id', $filters['category_id']))
            ->when(filled($filters['brand_id']), fn (Builder $query) => $query->where('brand_id', $filters['brand_id']))
            ->orderBy('name')->get()->map(function (ProductService $product) use ($totals): ProductService {
                $product->setAttribute('report_quantity', $totals[$product->id] ?? '0.0000');

                return $product;
            })->filter(fn (ProductService $product): bool => bccomp($product->report_quantity, $product->reorder_level, 4) <= 0)->values();
    }

    /** @param array<string, mixed> $filters */
    public function inTransit(array $filters): Collection
    {
        return InventoryTransferLine::query()
            ->with(['product:id,sku,name', 'transfer.sourceWarehouse:id,code', 'transfer.destinationWarehouse:id,code'])
            ->whereHas('transfer', fn (Builder $query) => $query
                ->whereIn('status', [InventoryTransferStatus::Released, InventoryTransferStatus::InTransit])
                ->whereDate('transfer_date', '<=', $filters['as_of'])
                ->when(filled($filters['warehouse_id']), fn (Builder $query) => $query->where(
                    fn (Builder $query) => $query->where('source_warehouse_id', $filters['warehouse_id'])
                        ->orWhere('destination_warehouse_id', $filters['warehouse_id'])
                )))
            ->when(filled($filters['product_service_id']), fn (Builder $query) => $query->where('product_service_id', $filters['product_service_id']))
            ->limit(50)->get();
    }

    /** @param array<string, mixed> $filters */
    public function slowMoving(array $filters): Collection
    {
        return ProductService::query()->where('type', 'product')->where('is_inventory', true)
            ->when(filled($filters['product_service_id']), fn (Builder $query) => $query->whereKey($filters['product_service_id']))
            ->when(filled($filters['category_id']), fn (Builder $query) => $query->where('category_id', $filters['category_id']))
            ->when(filled($filters['brand_id']), fn (Builder $query) => $query->where('brand_id', $filters['brand_id']))
            ->addSelect(['last_movement_date' => InventoryMovement::query()->select('movement_date')
                ->whereColumn('product_service_id', 'product_services.id')
                ->where('status', InventoryMovementStatus::Posted)->whereDate('movement_date', '<=', $filters['as_of'])
                ->latest('movement_date')->latest('id')->limit(1)])
            ->withCasts(['last_movement_date' => 'date'])
            ->where(fn (Builder $query) => $query->whereDoesntHave('inventoryMovements', fn (Builder $query) => $query
                ->where('status', InventoryMovementStatus::Posted)->whereDate('movement_date', '<=', $filters['as_of']))
                ->orWhereDoesntHave('inventoryMovements', fn (Builder $query) => $query->where('status', InventoryMovementStatus::Posted)
                    ->whereBetween('movement_date', [$filters['start_date'], $filters['as_of']])))
            ->orderBy('name')->limit(50)->get();
    }

    /** @param array<string, mixed> $filters */
    public function damagedAndAdjusted(array $filters): Collection
    {
        return $this->movementQuery($filters)->with(['product:id,sku,name', 'warehouse:id,code'])
            ->whereIn('type', [InventoryMovementType::AdjustmentIn, InventoryMovementType::AdjustmentOut,
                InventoryMovementType::PhysicalCountGain, InventoryMovementType::PhysicalCountLoss])
            ->latest('movement_date')->limit(50)->get();
    }

    public function sourceReference(InventoryMovement $movement): string
    {
        return match (true) {
            $movement->inventory_opening_balance_line_id !== null => 'Opening balance line #'.$movement->inventory_opening_balance_line_id,
            $movement->receiving_record_line_id !== null => 'Receiving line #'.$movement->receiving_record_line_id,
            $movement->delivery_line_id !== null => 'Delivery line #'.$movement->delivery_line_id,
            $movement->inventory_adjustment_line_id !== null => 'Adjustment line #'.$movement->inventory_adjustment_line_id,
            $movement->inventory_transfer_line_id !== null => 'Transfer line #'.$movement->inventory_transfer_line_id,
            $movement->physical_count_line_id !== null => 'Physical count line #'.$movement->physical_count_line_id,
            $movement->reversal_of_id !== null => 'Reversal #'.$movement->reversal_of_id,
            default => 'Movement #'.$movement->id,
        };
    }

    /** @param array<string, mixed> $filters
     * @return Builder<InventoryMovement>
     */
    public function stockQuery(array $filters): Builder
    {
        $movementTable = (new InventoryMovement)->getTable();
        $stockAlias = 'stock_movements';
        $latestCost = InventoryMovement::query()->select('balance_average_cost_after')
            ->whereColumn($movementTable.'.product_service_id', $stockAlias.'.product_service_id')
            ->whereColumn($movementTable.'.warehouse_id', $stockAlias.'.warehouse_id')
            ->where('status', InventoryMovementStatus::Posted)
            ->whereDate('movement_date', '<=', $filters['as_of'])
            ->latest('movement_date')->latest('id')->limit(1);

        return InventoryMovement::query()->from($movementTable.' as '.$stockAlias)
            ->where('status', InventoryMovementStatus::Posted)
            ->whereIn('product_service_id', $this->productIds($filters))
            ->when(filled($filters['warehouse_id']), fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->whereDate('movement_date', '<=', $filters['as_of'])
            ->select(['product_service_id', 'warehouse_id'])
            ->selectRaw('SUM(quantity) as quantity')
            ->addSelect(['as_of_average_cost' => $latestCost])
            ->groupBy('product_service_id', 'warehouse_id')
            ->havingRaw('SUM(quantity) <> 0')
            ->orderBy('product_service_id')->orderBy('warehouse_id');
    }

    /** @param array<string, mixed> $filters
     * @return Builder<InventoryMovement>
     */
    public function movementQuery(array $filters, bool $rangeOnly = true): Builder
    {
        return InventoryMovement::query()->where('status', InventoryMovementStatus::Posted)
            ->whereIn('product_service_id', $this->productIds($filters))
            ->when(filled($filters['warehouse_id']), fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(filled($filters['movement_type']), fn (Builder $query) => $query->where('type', $filters['movement_type']))
            ->when($rangeOnly, fn (Builder $query) => $query->whereBetween('movement_date', [$filters['start_date'], $filters['end_date']]))
            ->when(! $rangeOnly, fn (Builder $query) => $query->whereDate('movement_date', '<=', $filters['end_date']));
    }

    /** @param array<string, mixed> $filters */
    private function productIds(array $filters): Builder
    {
        return ProductService::query()->select('id')->where('type', 'product')->where('is_inventory', true)
            ->when(filled($filters['product_service_id']), fn (Builder $query) => $query->whereKey($filters['product_service_id']))
            ->when(filled($filters['category_id']), fn (Builder $query) => $query->where('category_id', $filters['category_id']))
            ->when(filled($filters['brand_id']), fn (Builder $query) => $query->where('brand_id', $filters['brand_id']));
    }

    /** @return array<int, string> */
    private function sourceRelations(): array
    {
        return ['product:id,sku,name', 'warehouse:id,code,name', 'openingBalanceLine.openingBalance',
            'receivingRecordLine.receivingRecord', 'deliveryLine.delivery', 'adjustmentLine.adjustment',
            'transferLine.transfer', 'physicalCountLine.count'];
    }

    /** @param EloquentCollection<int, InventoryMovement> $stocks */
    private function prepareStocks(EloquentCollection $stocks): void
    {
        $stocks->load(['product:id,sku,name,category_id,brand_id,reorder_level', 'product.category:id,name',
            'product.brand:id,name', 'warehouse:id,code,name']);
        $stocks->each(fn (InventoryMovement $stock) => $stock->setAttribute(
            'as_of_value', bcmul($stock->quantity, $stock->as_of_average_cost ?? '0.0000', 4)
        ));
    }
}
