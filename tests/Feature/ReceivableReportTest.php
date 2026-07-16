<?php

use App\Enums\CustomerPaymentStatus;
use App\Enums\PaymentAllocationStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FiscalPeriod;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Reports\ReceivablesReport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function receivableFixture(string $customerType = 'private'): array
{
    $user = User::factory()->administrator()->create();
    $customer = Customer::factory()->create(['type' => $customerType]);
    $period = FiscalPeriod::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);

    return compact('user', 'customer', 'period');
}

function receivableInvoice(array $fixture, string $dueDate, string $amount = '1000.0000', array $overrides = []): SalesInvoice
{
    return SalesInvoice::factory()->for($fixture['customer'])->for($fixture['period'])->create(array_replace([
        'invoice_number' => fake()->unique()->numerify('SI-######'), 'invoice_date' => '2026-01-01', 'due_date' => $dueDate,
        'total_receivable' => $amount, 'paid_amount' => '0.0000', 'balance_due' => $amount,
        'status' => SalesInvoiceStatus::Posted, 'posted_at' => '2026-01-01', 'posted_by' => $fixture['user']->id,
    ], $overrides));
}

function allocateReceivable(array $fixture, SalesInvoice $invoice, string $amount, string $date = '2026-07-01', array $overrides = []): PaymentAllocation
{
    $method = PaymentMethod::factory()->create();
    $payment = CustomerPayment::factory()->for($fixture['customer'])->for($method)->create(array_replace([
        'payment_number' => fake()->unique()->numerify('CR-######'), 'payment_date' => $date,
        'gross_settlement_amount' => $amount, 'net_cash_received' => $amount, 'unapplied_amount' => '0.0000',
        'status' => CustomerPaymentStatus::FullyAllocated, 'posted_at' => $date, 'posted_by' => $fixture['user']->id,
        'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id,
    ], $overrides));

    return PaymentAllocation::factory()->for($payment)->for($invoice)->create([
        'amount' => $amount, 'status' => PaymentAllocationStatus::Active, 'allocated_at' => $date, 'allocated_by' => $fixture['user']->id,
    ]);
}

test('aging assigns every due-date bucket accurately', function () {
    $fixture = receivableFixture();
    foreach (['2026-07-31', '2026-07-01', '2026-06-30', '2026-06-01', '2026-05-31', '2026-05-02', '2026-04-30'] as $dueDate) {
        receivableInvoice($fixture, $dueDate, '100.0000');
    }
    $rows = app(ReceivablesReport::class)->detailCollection(['as_of' => '2026-07-31']);

    expect($rows->pluck('bucket')->all())->toBe(['over-90', '61-90', '61-90', '31-60', '31-60', '1-30', 'current'])
        ->and($rows->pluck('daysOverdue')->all())->toBe([92, 90, 61, 60, 31, 30, 0]);
});

test('partial full and future allocations are reflected by as-of date', function () {
    $fixture = receivableFixture();
    $partial = receivableInvoice($fixture, '2026-06-30');
    $full = receivableInvoice($fixture, '2026-06-30', '500.0000');
    allocateReceivable($fixture, $partial, '250.0000', '2026-07-10');
    allocateReceivable($fixture, $full, '500.0000', '2026-07-10');

    $before = app(ReceivablesReport::class)->detailCollection(['as_of' => '2026-07-09']);
    $after = app(ReceivablesReport::class)->detailCollection(['as_of' => '2026-07-31']);
    expect($before->sum(fn (array $row): float => (float) $row['balance']))->toBe(1500.0)
        ->and($after)->toHaveCount(1)->and($after->sole()['invoice']->is($partial))->toBeTrue()
        ->and($after->sole()['allocated'])->toBe('250.0000')->and($after->sole()['balance'])->toBe('750.0000');
});

test('voided invoices and voided or reversed allocations are excluded', function () {
    $fixture = receivableFixture();
    receivableInvoice($fixture, '2026-06-30', '400.0000', ['status' => SalesInvoiceStatus::Voided]);
    $open = receivableInvoice($fixture, '2026-06-30', '600.0000');
    allocateReceivable($fixture, $open, '200.0000', '2026-07-01', ['status' => CustomerPaymentStatus::Voided]);
    $reversed = allocateReceivable($fixture, $open, '100.0000');
    $reversed->update(['status' => PaymentAllocationStatus::Reversed, 'reversed_at' => '2026-07-02', 'reversed_by' => $fixture['user']->id]);

    $row = app(ReceivablesReport::class)->detailCollection(['as_of' => '2026-07-31'])->sole();
    expect($row['balance'])->toBe('600.0000')->and($row['allocated'])->toBe('0.0000');
});

test('government private state and bucket filters work and summary reconciles', function () {
    $private = receivableFixture('private');
    $government = receivableFixture('government');
    $privateInvoice = receivableInvoice($private, '2026-07-20', '300.0000');
    receivableInvoice($government, '2026-05-20', '700.0000');
    allocateReceivable($private, $privateInvoice, '100.0000');
    $report = app(ReceivablesReport::class);

    $governmentRows = $report->detailCollection(['as_of' => '2026-07-31', 'customer_type' => 'government', 'bucket' => '61-90']);
    $partialRows = $report->detailCollection(['as_of' => '2026-07-31', 'state' => 'partial']);
    $all = $report->detailCollection(['as_of' => '2026-07-31']);
    $summary = $report->summary($all);
    expect($governmentRows)->toHaveCount(1)->and($governmentRows->sole()['balance'])->toBe('700.0000')
        ->and($partialRows)->toHaveCount(1)->and($partialRows->sole()['balance'])->toBe('200.0000')
        ->and($summary->reduce(fn (string $total, array $row): string => bcadd($total, $row['total'], 4), '0.0000'))->toBe('900.0000');
});

test('detail summary statements unapplied print and csv endpoints work', function () {
    $fixture = receivableFixture();
    $invoice = receivableInvoice($fixture, '2026-06-30');
    allocateReceivable($fixture, $invoice, '250.0000');
    CustomerPayment::factory()->for($fixture['customer'])->create(['payment_number' => 'CR-ADVANCE', 'payment_date' => '2026-07-20',
        'gross_settlement_amount' => '300.0000', 'net_cash_received' => '300.0000', 'unapplied_amount' => '300.0000',
        'status' => CustomerPaymentStatus::Posted, 'posted_at' => '2026-07-20', 'posted_by' => $fixture['user']->id,
        'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);

    $query = ['as_of' => '2026-07-31'];
    $this->actingAs($fixture['user'])->get(route('receivables.index', $query))->assertSuccessful()->assertSee('750.00');
    $this->actingAs($fixture['user'])->get(route('receivables.summary', $query))->assertSuccessful()->assertSee($fixture['customer']->name);
    $this->actingAs($fixture['user'])->get(route('receivables.unapplied', $query))->assertSuccessful()->assertSee('CR-ADVANCE');
    $this->actingAs($fixture['user'])->get(route('receivables.print', $query))->assertSuccessful();
    $this->actingAs($fixture['user'])->get(route('customer-statements.show', [$fixture['customer'], ...$query]))->assertSuccessful()->assertSee('750.00');
    $this->actingAs($fixture['user'])->get(route('customer-statements.print', [$fixture['customer'], ...$query]))->assertSuccessful();
    $this->actingAs($fixture['user'])->get(route('receivables.export', $query))->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertDownload('receivables-2026-07-31.csv');
});

test('receivables detail is paginated', function () {
    $fixture = receivableFixture();
    foreach (range(1, 26) as $number) {
        receivableInvoice($fixture, '2026-07-15', '10.0000', ['invoice_number' => 'SI-PAGE-'.$number]);
    }

    $this->actingAs($fixture['user'])->get(route('receivables.index', ['as_of' => '2026-07-31']))
        ->assertSuccessful()->assertViewHas('rows', fn ($rows): bool => $rows->total() === 26 && $rows->lastPage() === 2);
});

test('report validation and all three permissions are enforced without a ledger', function () {
    $fixture = receivableFixture();
    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)->get(route('receivables.index'))->assertForbidden();
    $this->actingAs($unauthorized)->get(route('customer-statements.show', $fixture['customer']))->assertForbidden();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('receivables.index', ['as_of' => 'invalid']))->assertSessionHasErrors('as_of');
    $this->actingAs($viewer)->get(route('receivables.export', ['as_of' => '2026-07-31']))->assertForbidden();
    expect(Schema::hasTable('receivable_ledgers'))->toBeFalse()->and(Schema::hasTable('journal_entries'))->toBeFalse();
});
