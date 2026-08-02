<?php

use App\Enums\SalesInvoiceStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Reports\AccountsPayableReport;
use App\Reports\ReceivablesReport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function performanceAgingFixture(int $invoiceCount = 60): array
{
    $user = User::factory()->administrator()->create();
    $customer = Customer::factory()->create();
    $supplier = Supplier::factory()->for($user, 'creator')->for($user, 'updater')->create();
    $period = FiscalPeriod::factory()->create([
        'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open',
    ]);

    foreach (range(1, $invoiceCount) as $number) {
        SalesInvoice::factory()->for($customer)->for($period)->create([
            'invoice_number' => "PERF-SI-{$number}", 'invoice_date' => '2026-01-01', 'due_date' => '2026-06-30',
            'total_receivable' => '100.0000', 'balance_due' => '100.0000', 'status' => SalesInvoiceStatus::Posted,
            'posted_at' => '2026-01-01', 'posted_by' => $user->id,
        ]);
        SupplierInvoice::query()->create([
            'supplier_id' => $supplier->id, 'fiscal_period_id' => $period->id,
            'internal_number' => "PERF-PI-{$number}", 'supplier_invoice_number' => "BILL-{$number}",
            'invoice_date' => '2026-01-01', 'due_date' => '2026-06-30', 'supplier_name' => $supplier->name,
            'gross_purchase_amount' => '100.0000', 'net_purchase_amount' => '100.0000',
            'total_payable' => '100.0000', 'balance_due' => '100.0000', 'status' => SupplierInvoiceStatus::Posted,
            'posted_at' => '2026-01-01', 'posted_by' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
    }

    return compact('user');
}

it('keeps critical listings paginated with bounded query counts', function (): void {
    $fixture = performanceAgingFixture(60);
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $response = $this->actingAs($fixture['user'])->get(route('sales-invoices.index'));

    $response->assertSuccessful()->assertViewHas('invoices', fn ($rows): bool => $rows->count() === 25 && $rows->total() === 60);
    expect($queries)->toBeLessThanOrEqual(8);
});

it('streams aging exports through bounded eager-loaded chunks', function (): void {
    performanceAgingFixture(60);
    $filters = ['as_of' => '2026-07-31'];
    $receivables = app(ReceivablesReport::class)->detailLazy($filters, 20);
    $payables = app(AccountsPayableReport::class)->detailLazy($filters, 20);

    expect($receivables)->toBeInstanceOf(LazyCollection::class)
        ->and($payables)->toBeInstanceOf(LazyCollection::class);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($receivables->count())->toBe(60)
        ->and($payables->count())->toBe(60)
        ->and($queries)->toBeLessThanOrEqual(14);
});

it('installs evidence-backed compound indexes for critical ordered filters', function (): void {
    $indexes = fn (string $table): array => collect(Schema::getIndexes($table))->pluck('name')->all();

    expect($indexes('sales_invoices'))->toContain('sales_invoice_aging_order_index')
        ->and($indexes('supplier_invoices'))->toContain('supplier_invoice_aging_order_index')
        ->and($indexes('journal_entries'))->toContain('journal_status_date_order_index')
        ->and($indexes('audit_logs'))->toContain('audit_occurred_order_index');
});
