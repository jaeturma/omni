<?php

use App\Enums\CustomerPaymentStatus;
use App\Enums\DeliveryStatus;
use App\Enums\PaymentAllocationStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Models\DocumentSequence;
use App\Support\SalesAmountCalculator;
use App\Support\SalesWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

test('sales status transitions are explicit and terminal states are immutable', function () {
    expect(QuotationStatus::Draft->canTransitionTo(QuotationStatus::Sent))->toBeTrue()
        ->and(QuotationStatus::Accepted->allowedTransitions())->toBeEmpty()
        ->and(SalesOrderStatus::Confirmed->canTransitionTo(SalesOrderStatus::PartiallyDelivered))->toBeTrue()
        ->and(SalesOrderStatus::Completed->allowedTransitions())->toBeEmpty()
        ->and(DeliveryStatus::Released->canTransitionTo(DeliveryStatus::Delivered))->toBeTrue()
        ->and(DeliveryStatus::Delivered->allowedTransitions())->toBeEmpty()
        ->and(SalesInvoiceStatus::Draft->canTransitionTo(SalesInvoiceStatus::Posted))->toBeTrue()
        ->and(SalesInvoiceStatus::Posted->canTransitionTo(SalesInvoiceStatus::Draft))->toBeFalse()
        ->and(SalesInvoiceStatus::Voided->allowedTransitions())->toBeEmpty()
        ->and(CustomerPaymentStatus::Posted->canTransitionTo(CustomerPaymentStatus::FullyAllocated))->toBeTrue()
        ->and(CustomerPaymentStatus::FullyAllocated->allowedTransitions())->toBeEmpty()
        ->and(PaymentAllocationStatus::Active->canTransitionTo(PaymentAllocationStatus::Reversed))->toBeTrue()
        ->and(PaymentAllocationStatus::Reversed->allowedTransitions())->toBeEmpty();
});

test('sales amount calculations use decimal strings and keep deductions separate', function () {
    $calculator = new SalesAmountCalculator;

    expect($calculator->line('3.3333', '125.5555', '7.125000'))->toBe([
        'gross_amount' => '418.5141',
        'discount_amount' => '29.8191',
        'net_amount' => '388.6950',
    ])->and($calculator->settlement('1000.0000', '50.0000', '20.0000', '700.0000'))->toBe([
        'gross_sales' => '1000.0000',
        'discounts' => '50.0000',
        'net_sales' => '950.0000',
        'withholding' => '20.0000',
        'cash_received' => '700.0000',
        'balance_due' => '230.0000',
    ]);
});

test('sales amount calculations reject invalid rates and over-settlement', function () {
    $calculator = new SalesAmountCalculator;

    expect(fn () => $calculator->line('1', '100', '100.000001'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calculator->settlement('100.0000', '10.0000', '20.0000', '80.0001'))->toThrow(InvalidArgumentException::class);
});

test('phase three permissions are seeded without transaction records', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->whereIn('name', SalesWorkflow::PERMISSIONS)->count())->toBe(count(SalesWorkflow::PERMISSIONS))
        ->and(Role::findByName('Administrator')->hasAllPermissions(SalesWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Owner')->hasAllPermissions(SalesWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Bookkeeper')->hasAllPermissions(SalesWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Encoder')->hasAllPermissions(SalesWorkflow::ENCODER_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasAllPermissions(SalesWorkflow::VIEW_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasPermissionTo('sales-invoices.create'))->toBeFalse()
        ->and(Schema::hasTable('quotations'))->toBeFalse()
        ->and(Schema::hasTable('sales_orders'))->toBeFalse()
        ->and(Schema::hasTable('deliveries'))->toBeFalse()
        ->and(Schema::hasTable('sales_invoices'))->toBeFalse()
        ->and(Schema::hasTable('customer_payments'))->toBeFalse()
        ->and(Schema::hasTable('payment_allocations'))->toBeFalse();
});

test('sales documents map to controlled document sequence codes', function () {
    expect(SalesWorkflow::DOCUMENT_SEQUENCES)->toBe([
        'quotation' => 'quotation',
        'sales_order' => 'sales_order',
        'delivery' => 'delivery_receipt',
        'sales_invoice' => 'sales_invoice',
        'customer_payment' => 'collection_receipt',
    ]);

    foreach (SalesWorkflow::DOCUMENT_SEQUENCES as $documentType) {
        expect(DocumentSequence::TYPES)->toContain($documentType);
    }
});
