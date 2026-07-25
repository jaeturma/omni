<?php

namespace App\Support;

use App\Enums\InventoryMovementType;
use App\Models\ProductService;
use DomainException;

final class InventoryWorkflow
{
    public const COSTING_METHOD = 'weighted_average';

    public const COSTING_SCOPE = 'product_warehouse';

    public const COST_SCALE = 4;

    public const QUANTITY_SCALE = 4;

    public const NEGATIVE_STOCK_ALLOWED = false;

    public const DOCUMENT_SEQUENCES = [
        'opening_balance' => 'inventory_opening_balance',
        'adjustment' => 'inventory_adjustment',
        'transfer' => 'inventory_transfer',
        'physical_count' => 'inventory_physical_count',
    ];

    public const SOURCE_LINKS = [
        InventoryMovementType::PurchaseReceipt->value => 'receiving_record',
        InventoryMovementType::SalesIssue->value => 'delivery',
    ];

    public const PERMISSIONS = [
        'inventory-opening.view', 'inventory-opening.create', 'inventory-opening.post', 'inventory-opening.void',
        'inventory-receipts.view', 'inventory-receipts.post', 'inventory-receipts.reverse',
        'inventory-issues.view', 'inventory-issues.post', 'inventory-issues.reverse',
        'inventory-movements.view', 'inventory-movements.post', 'inventory-movements.reverse',
        'inventory-opening-balances.manage', 'inventory-adjustments.manage', 'inventory-transfers.manage',
        'inventory-counts.manage', 'inventory-costing.view', 'inventory-reports.view', 'inventory-reports.export',
    ];

    public const ENCODER_PERMISSIONS = [
        'inventory-opening.view', 'inventory-opening.create',
        'inventory-receipts.view',
        'inventory-issues.view',
        'inventory-movements.view', 'inventory-opening-balances.manage', 'inventory-adjustments.manage',
        'inventory-transfers.manage', 'inventory-counts.manage', 'inventory-costing.view', 'inventory-reports.view',
    ];

    public const VIEW_PERMISSIONS = [
        'inventory-opening.view', 'inventory-receipts.view', 'inventory-issues.view', 'inventory-movements.view', 'inventory-costing.view', 'inventory-reports.view',
    ];

    public static function tracks(ProductService $product): bool
    {
        return $product->type === 'product' && $product->is_inventory;
    }

    public static function assertStockAvailable(string $available, string $requested): void
    {
        if (bccomp($requested, $available, self::QUANTITY_SCALE) === 1) {
            throw new DomainException('Insufficient stock is available for this movement.');
        }
    }

    private function __construct() {}
}
