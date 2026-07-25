<?php

namespace App\Http\Controllers;

use App\Enums\InventoryMovementType;
use App\Http\Requests\InventoryReportRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\Warehouse;
use App\Reports\InventoryStockReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportController extends Controller
{
    public function index(InventoryReportRequest $request, InventoryStockReport $report): View
    {
        $filters = $request->validated();

        return view('inventory-reports.index', $this->viewData($filters, $report) + [
            'stocks' => $report->stockPaginator($filters),
            'ledger' => $report->ledgerPaginator($filters),
        ]);
    }

    public function print(InventoryReportRequest $request, InventoryStockReport $report): View
    {
        $filters = $request->validated();

        return view('inventory-reports.print', [
            'filters' => $filters,
            'summary' => $report->summary($filters, Gate::allows('inventory-valuation.view')),
            'stocks' => $report->stockRows($filters),
            'canViewCost' => Gate::allows('inventory-cost.view'),
            'canViewValuation' => Gate::allows('inventory-valuation.view'),
        ]);
    }

    public function export(InventoryReportRequest $request, InventoryStockReport $report): StreamedResponse
    {
        Gate::authorize('inventory-reports.export');
        $filters = $request->validated();
        $canViewCost = Gate::allows('inventory-cost.view');
        $canViewValuation = Gate::allows('inventory-valuation.view');

        return response()->streamDownload(function () use ($report, $filters, $canViewCost, $canViewValuation): void {
            $stream = fopen('php://output', 'w');
            $headings = ['Date', 'Product', 'Warehouse', 'Movement Type', 'Source', 'Quantity'];
            if ($canViewCost) {
                $headings[] = 'Unit Cost';
            }
            if ($canViewValuation) {
                $headings[] = 'Value';
            }
            fputcsv($stream, $headings);
            $report->movementQuery($filters)->with(['product:id,sku,name', 'warehouse:id,code', 'openingBalanceLine.openingBalance',
                'receivingRecordLine.receivingRecord', 'deliveryLine.delivery', 'adjustmentLine.adjustment',
                'transferLine.transfer', 'physicalCountLine.count'])->chunkById(200, function (Collection $movements) use ($stream, $report, $canViewCost, $canViewValuation): void {
                    $movements->each(function (InventoryMovement $movement) use ($stream, $report, $canViewCost, $canViewValuation): void {
                        $row = [$movement->movement_date->toDateString(), $movement->product->sku.' - '.$movement->product->name,
                            $movement->warehouse->code, $movement->type->value, $report->sourceReference($movement), $movement->quantity];
                        if ($canViewCost) {
                            $row[] = $movement->unit_cost;
                        }
                        if ($canViewValuation) {
                            $row[] = $movement->total_cost;
                        }
                        fputcsv($stream, $row);
                    });
                });
            fclose($stream);
        }, 'inventory-ledger-'.$filters['start_date'].'-'.$filters['end_date'].'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function viewData(array $filters, InventoryStockReport $report): array
    {
        $canViewValuation = Gate::allows('inventory-valuation.view');

        return [
            'filters' => $filters,
            'summary' => $report->summary($filters, $canViewValuation),
            'alerts' => $report->alerts($filters),
            'negativeStocks' => $report->negativeStocks($filters),
            'inTransit' => $report->inTransit($filters),
            'slowMoving' => $report->slowMoving($filters),
            'adjustments' => $report->damagedAndAdjusted($filters),
            'products' => ProductService::query()->where('type', 'product')->where('is_inventory', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'categories' => Category::query()->where('type', 'product')->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name']),
            'movementTypes' => InventoryMovementType::cases(),
            'canViewCost' => Gate::allows('inventory-cost.view'),
            'canViewValuation' => $canViewValuation,
        ];
    }
}
