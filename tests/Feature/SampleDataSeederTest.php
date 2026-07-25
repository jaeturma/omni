<?php

use App\Models\CashDisbursement;
use App\Models\CashReceipt;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('sample data seeder creates a coherent and rerunnable demo dataset', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SampleDataSeeder::class);
    $this->seed(SampleDataSeeder::class);

    expect(User::query()->where('email', 'demo@omni.app')->count())->toBe(1)
        ->and(Customer::query()->whereIn('code', ['CUS-DEPED', 'CUS-PRIVATE'])->count())->toBe(2)
        ->and(ProductService::query()->whereIn('sku', ['ICT-LAP-001', 'ICT-PRN-001', 'OFF-PAP-001', 'SVC-INSTALL'])->count())->toBe(4)
        ->and(FinancialAccount::query()->whereIn('code', ['BANK-BDO-001', 'CASH-ON-HAND'])->count())->toBe(2)
        ->and(Quotation::query()->where('reference', 'DEMO-RFQ-001')->firstOrFail()->lines)->toHaveCount(3)
        ->and(SalesOrder::query()->where('customer_po_number', 'DEMO-PO-2026-001')->firstOrFail()->lines)->toHaveCount(2)
        ->and(SalesInvoice::query()->where('notes', 'Sample direct sales invoice.')->firstOrFail()->lines)->toHaveCount(1)
        ->and(CashReceipt::query()->where('reference_number', 'DEMO-DEP-001')->count())->toBe(1)
        ->and(CashDisbursement::query()->where('reference_number', 'DEMO-PAY-001')->count())->toBe(1);
});
