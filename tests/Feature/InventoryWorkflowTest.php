<?php

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Models\DocumentSequence;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

test('inventory movement and posting statuses are explicit and append only', function () {
    expect(array_column(InventoryMovementType::cases(), 'value'))->toBe([
        'opening_balance', 'purchase_receipt', 'sales_issue', 'customer_return', 'supplier_return',
        'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'physical_count_gain', 'physical_count_loss',
    ])->and(array_column(InventoryMovementStatus::cases(), 'value'))->toBe(['draft', 'posted', 'reversed'])
        ->and(InventoryMovementStatus::Draft->canTransitionTo(InventoryMovementStatus::Posted))->toBeTrue()
        ->and(InventoryMovementStatus::Posted->canTransitionTo(InventoryMovementStatus::Draft))->toBeFalse()
        ->and(InventoryMovementStatus::Posted->canTransitionTo(InventoryMovementStatus::Reversed))->toBeTrue()
        ->and(InventoryMovementStatus::Reversed->allowedTransitions())->toBeEmpty();
});

test('inventory conventions centralize precision costing sources and sequences', function () {
    expect(InventoryWorkflow::COSTING_METHOD)->toBe('weighted_average')
        ->and(InventoryWorkflow::COSTING_SCOPE)->toBe('product_warehouse')
        ->and(InventoryWorkflow::COST_SCALE)->toBe(4)
        ->and(InventoryWorkflow::QUANTITY_SCALE)->toBe(4)
        ->and(InventoryWorkflow::SOURCE_LINKS)->toBe([
            'purchase_receipt' => 'receiving_record',
            'sales_issue' => 'delivery',
        ]);

    foreach (InventoryWorkflow::DOCUMENT_SEQUENCES as $documentType) {
        expect(DocumentSequence::TYPES)->toContain($documentType);
    }
});

test('negative stock is blocked by default', function () {
    expect(InventoryWorkflow::NEGATIVE_STOCK_ALLOWED)->toBeFalse();
    InventoryWorkflow::assertStockAvailable('10.0000', '10.0000');

    expect(fn () => InventoryWorkflow::assertStockAvailable('10.0000', '10.0001'))
        ->toThrow(DomainException::class, 'Insufficient stock');
});

test('services and non inventory products are excluded from stock', function () {
    $inventoryProduct = new ProductService(['type' => 'product', 'is_inventory' => true]);
    $nonInventoryProduct = new ProductService(['type' => 'product', 'is_inventory' => false]);
    $service = new ProductService(['type' => 'service', 'is_inventory' => true]);

    expect(InventoryWorkflow::tracks($inventoryProduct))->toBeTrue()
        ->and(InventoryWorkflow::tracks($nonInventoryProduct))->toBeFalse()
        ->and(InventoryWorkflow::tracks($service))->toBeFalse();
});

test('phase six permissions are seeded by role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->whereIn('name', InventoryWorkflow::PERMISSIONS)->count())->toBe(count(InventoryWorkflow::PERMISSIONS))
        ->and(Role::findByName('Administrator')->hasAllPermissions(InventoryWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Owner')->hasAllPermissions(InventoryWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Bookkeeper')->hasAllPermissions(InventoryWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Encoder')->hasAllPermissions(InventoryWorkflow::ENCODER_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasAllPermissions(InventoryWorkflow::VIEW_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasPermissionTo('inventory-movements.post'))->toBeFalse();
});

test('inventory and journal transaction tables are not created by the conventions package', function () {
    foreach (['inventory_movements', 'inventory_balances', 'inventory_cost_layers', 'journal_entries', 'journal_entry_lines'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});
