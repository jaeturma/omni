<?php

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPaymentAllocationStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\FiscalPeriod;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use App\Reports\AccountsPayableReport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function payableFixture(): array
{
    $user = User::factory()->administrator()->create();
    $supplier = Supplier::factory()->for($user, 'creator')->for($user, 'updater')->create();
    $period = FiscalPeriod::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);

    return compact('user', 'supplier', 'period');
}

function payableInvoice(array $f, string $dueDate, string $amount = '1000.0000', array $overrides = []): SupplierInvoice
{
    return SupplierInvoice::query()->create(array_replace(['supplier_id' => $f['supplier']->id, 'fiscal_period_id' => $f['period']->id,
        'supplier_invoice_number' => fake()->unique()->numerify('BILL-######'), 'invoice_date' => '2026-01-01', 'due_date' => $dueDate,
        'supplier_name' => $f['supplier']->name, 'gross_purchase_amount' => $amount, 'net_purchase_amount' => $amount,
        'total_payable' => $amount, 'paid_amount' => '0.0000', 'balance_due' => $amount, 'status' => SupplierInvoiceStatus::Posted,
        'posted_at' => '2026-01-01', 'posted_by' => $f['user']->id, 'created_by' => $f['user']->id, 'updated_by' => $f['user']->id], $overrides));
}

function allocatePayable(array $f, SupplierInvoice $invoice, string $amount, string $date = '2026-07-10'): SupplierPaymentAllocation
{
    $method = PaymentMethod::factory()->create();
    $payment = SupplierPayment::query()->create(['supplier_id' => $f['supplier']->id, 'payment_method_id' => $method->id,
        'payment_number' => fake()->unique()->numerify('SP-######'), 'payment_date' => $date, 'gross_settlement_amount' => $amount,
        'net_cash_paid' => $amount, 'unapplied_amount' => '0.0000', 'status' => SupplierPaymentStatus::FullyAllocated,
        'posted_at' => $date, 'posted_by' => $f['user']->id, 'created_by' => $f['user']->id, 'updated_by' => $f['user']->id]);

    return SupplierPaymentAllocation::query()->create(['supplier_payment_id' => $payment->id, 'supplier_invoice_id' => $invoice->id,
        'amount' => $amount, 'status' => SupplierPaymentAllocationStatus::Active, 'allocated_at' => $date, 'allocated_by' => $f['user']->id]);
}

test('payable aging assigns due-date buckets', function () {
    $f = payableFixture();
    foreach (['2026-04-30', '2026-05-02', '2026-06-01', '2026-07-01', '2026-07-31'] as $date) {
        payableInvoice($f, $date, '100.0000');
    }
    $rows = app(AccountsPayableReport::class)->detailCollection(['as_of' => '2026-07-31']);
    expect($rows->pluck('bucket')->all())->toBe(['over-90', '61-90', '31-60', '1-30', 'current']);
});

test('partial full and future allocations respect the as-of date', function () {
    $f = payableFixture();
    $partial = payableInvoice($f, '2026-06-30');
    $full = payableInvoice($f, '2026-06-30', '500.0000');
    allocatePayable($f, $partial, '250.0000');
    allocatePayable($f, $full, '500.0000');
    expect(app(AccountsPayableReport::class)->detailCollection(['as_of' => '2026-07-09']))->toHaveCount(2);
    $after = app(AccountsPayableReport::class)->detailCollection(['as_of' => '2026-07-31']);
    expect($after)->toHaveCount(1)->and($after->sole()['balance'])->toBe('750.0000');
});

test('voided invoices and reversed allocations do not reduce balances', function () {
    $f = payableFixture();
    payableInvoice($f, '2026-06-30', '400.0000', ['status' => SupplierInvoiceStatus::Voided]);
    $open = payableInvoice($f, '2026-06-30', '600.0000');
    $allocation = allocatePayable($f, $open, '100.0000');
    $allocation->update(['status' => SupplierPaymentAllocationStatus::Reversed, 'reversed_at' => '2026-07-20']);
    expect(app(AccountsPayableReport::class)->detailCollection(['as_of' => '2026-07-31'])->sole()['balance'])->toBe('600.0000');
});

test('supplier filtering and summary reconcile to detail', function () {
    $f = payableFixture();
    payableInvoice($f, '2026-06-30', '700.0000');
    $other = payableFixture();
    payableInvoice($other, '2026-06-30', '300.0000');
    $report = app(AccountsPayableReport::class);
    $detail = $report->detailCollection(['as_of' => '2026-07-31', 'supplier_id' => $f['supplier']->id]);
    expect($detail)->toHaveCount(1)->and($report->summary($detail)->sole()['total'])->toBe('700.0000');
});

test('payables endpoints enforce viewing and export permissions', function () {
    $f = payableFixture();
    payableInvoice($f, '2026-06-30');
    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)->get(route('payables.index'))->assertForbidden();
    $this->actingAs($f['user'])->get(route('payables.index', ['as_of' => '2026-07-31']))->assertOk()->assertSee('Accounts Payable Aging');
    $this->get(route('payables.export', ['as_of' => '2026-07-31']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $this->get(route('supplier-statements.show', [$f['supplier'], 'as_of' => '2026-07-31']))->assertOk();
});

test('no accounts payable snapshot or subsidiary ledger table is introduced', function () {
    expect(Schema::hasTable('accounts_payable_snapshots'))->toBeFalse()->and(Schema::hasTable('supplier_ledgers'))->toBeFalse();
});
