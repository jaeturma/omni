<?php

use App\Enums\CanvassStatus;
use App\Enums\ExpenseStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\ReceivingStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPaymentAllocationStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\DocumentSequence;
use App\Support\PurchasingAmountCalculator;
use App\Support\PurchasingWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

test('purchasing status transitions are explicit and terminal states are immutable', function () {
    expect(PurchaseRequestStatus::Draft->canTransitionTo(PurchaseRequestStatus::Submitted))->toBeTrue()
        ->and(PurchaseRequestStatus::Converted->allowedTransitions())->toBeEmpty()
        ->and(CanvassStatus::Open->canTransitionTo(CanvassStatus::Evaluated))->toBeTrue()
        ->and(CanvassStatus::Awarded->allowedTransitions())->toBeEmpty()
        ->and(PurchaseOrderStatus::Issued->canTransitionTo(PurchaseOrderStatus::PartiallyReceived))->toBeTrue()
        ->and(PurchaseOrderStatus::Closed->allowedTransitions())->toBeEmpty()
        ->and(ReceivingStatus::Received->canTransitionTo(ReceivingStatus::Inspected))->toBeTrue()
        ->and(ReceivingStatus::Accepted->canTransitionTo(ReceivingStatus::Draft))->toBeFalse()
        ->and(ReceivingStatus::Cancelled->allowedTransitions())->toBeEmpty()
        ->and(SupplierInvoiceStatus::Draft->canTransitionTo(SupplierInvoiceStatus::Posted))->toBeTrue()
        ->and(SupplierInvoiceStatus::Posted->canTransitionTo(SupplierInvoiceStatus::Draft))->toBeFalse()
        ->and(SupplierPaymentStatus::Posted->canTransitionTo(SupplierPaymentStatus::FullyAllocated))->toBeTrue()
        ->and(SupplierPaymentStatus::FullyAllocated->canTransitionTo(SupplierPaymentStatus::Draft))->toBeFalse()
        ->and(ExpenseStatus::Approved->canTransitionTo(ExpenseStatus::Posted))->toBeTrue()
        ->and(ExpenseStatus::Voided->allowedTransitions())->toBeEmpty()
        ->and(SupplierPaymentAllocationStatus::Active->canTransitionTo(SupplierPaymentAllocationStatus::Reversed))->toBeTrue()
        ->and(SupplierPaymentAllocationStatus::Reversed->allowedTransitions())->toBeEmpty();
});

test('purchasing calculations use decimal strings and preserve freight and deductions', function () {
    $calculator = new PurchasingAmountCalculator;

    expect($calculator->line('3.3333', '125.5555', '7.125000'))->toBe([
        'gross_amount' => '418.5141', 'discount_amount' => '29.8191', 'net_amount' => '388.6950',
    ])->and($calculator->settlement('1000.0000', '50.0000', '75.0000', '20.0000', '700.0000'))->toBe([
        'gross_purchase' => '1000.0000', 'discounts' => '50.0000', 'net_purchase' => '950.0000',
        'freight' => '75.0000', 'total_due' => '1025.0000', 'withholding' => '20.0000',
        'cash_paid' => '700.0000', 'balance_due' => '305.0000',
    ]);
});

test('purchasing calculations reject invalid rates and over-settlement', function () {
    $calculator = new PurchasingAmountCalculator;

    expect(fn () => $calculator->line('1', '100', '100.000001'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calculator->settlement('100.0000', '10.0000', '5.0000', '20.0000', '75.0001'))->toThrow(InvalidArgumentException::class);
});

test('phase four permissions are seeded with purchase request canvass and purchase order tables', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->whereIn('name', PurchasingWorkflow::PERMISSIONS)->count())->toBe(count(PurchasingWorkflow::PERMISSIONS))
        ->and(Role::findByName('Administrator')->hasAllPermissions(PurchasingWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Owner')->hasAllPermissions(PurchasingWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Bookkeeper')->hasAllPermissions(PurchasingWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Encoder')->hasAllPermissions(PurchasingWorkflow::ENCODER_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasAllPermissions(PurchasingWorkflow::VIEW_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasPermissionTo('supplier-invoices.create'))->toBeFalse();

    foreach (['purchase_requests', 'purchase_request_lines', 'canvass_quotations', 'purchase_orders', 'purchase_order_lines', 'receiving_records', 'receiving_record_lines'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    foreach (['receivings', 'supplier_invoices', 'supplier_payments', 'supplier_payment_allocations', 'expenses'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});

test('purchasing documents map to controlled document sequence codes', function () {
    expect(PurchasingWorkflow::DOCUMENT_SEQUENCES)->toBe([
        'purchase_request' => 'purchase_request', 'purchase_order' => 'purchase_order',
        'receiving' => 'receiving_report', 'supplier_invoice' => 'purchase_invoice',
        'supplier_payment' => 'supplier_payment', 'expense' => 'expense_voucher',
    ]);

    foreach (PurchasingWorkflow::DOCUMENT_SEQUENCES as $documentType) {
        expect(DocumentSequence::TYPES)->toContain($documentType);
    }
});
