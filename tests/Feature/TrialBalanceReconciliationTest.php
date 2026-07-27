<?php

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Reports\SubledgerReconciliationReport;
use App\Reports\TrialBalanceReport;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function trialBalanceContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $year = FiscalYear::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'July 2026',
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31',
        'calendar_year' => 2026, 'calendar_month' => 7, 'calendar_quarter' => 3, 'status' => 'open',
    ]);
    $priorPeriod = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'June 2026',
        'starts_on' => '2026-06-01', 'ends_on' => '2026-06-30',
        'calendar_year' => 2026, 'calendar_month' => 6, 'calendar_quarter' => 2, 'status' => 'open',
    ]);
    $accounts = Account::query()->whereIn('code', ['1010', '1100', '1200', '2010', '3010'])
        ->get()->keyBy('code');

    return compact('user', 'year', 'period', 'priorPeriod', 'accounts');
}

function postTrialJournal(array $context, string $number, string $date, string $type, array $lines): void
{
    $entry = app(SaveJournalEntry::class)->handle([
        'journal_number' => $number,
        'journal_date' => $date,
        'document_date' => $date,
        'fiscal_period_id' => $date < '2026-07-01' ? $context['priorPeriod']->id : $context['period']->id,
        'journal_type' => $type,
        'source_type' => 'manual',
        'reference_number' => $number,
        'description' => $number,
        'lines' => $lines,
    ], $context['user']->id);
    app(TransitionJournalEntry::class)->handle($entry, JournalEntryStatus::Posted, $context['user']->id);
}

function trialFilters(array $changes = []): array
{
    return array_replace([
        'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'as_of' => '2026-07-31',
        'fiscal_period_id' => null, 'basis' => 'adjusted', 'account_id' => null, 'detail' => 'postable',
    ], $changes);
}

it('produces balanced unadjusted and adjusted trial balances with opening and movement', function (): void {
    $context = trialBalanceContext();
    $cash = $context['accounts']['1010'];
    $capital = $context['accounts']['3010'];
    foreach ([
        ['TB-OPEN', '2026-06-30', 'opening', '100.0000'],
        ['TB-REG', '2026-07-05', 'cash_receipt', '25.0000'],
        ['TB-ADJ', '2026-07-10', 'adjustment', '5.0000'],
    ] as [$number, $date, $type, $amount]) {
        postTrialJournal($context, $number, $date, $type, [
            ['account_id' => $cash->id, 'debit' => $amount, 'credit' => '0.0000'],
            ['account_id' => $capital->id, 'debit' => '0.0000', 'credit' => $amount],
        ]);
    }

    $unadjusted = app(TrialBalanceReport::class)->generate(trialFilters(['basis' => 'unadjusted']), false);
    $adjusted = app(TrialBalanceReport::class)->generate(trialFilters(), false);
    $cashUnadjusted = $unadjusted['rows']->firstWhere('account.id', $cash->id);
    $cashAdjusted = $adjusted['rows']->firstWhere('account.id', $cash->id);

    expect($unadjusted['balanced'])->toBeTrue()
        ->and($adjusted['balanced'])->toBeTrue()
        ->and($cashUnadjusted['opening_debit'])->toBe('100.0000')
        ->and($cashUnadjusted['movement_debit'])->toBe('25.0000')
        ->and($cashUnadjusted['closing_debit'])->toBe('125.0000')
        ->and($cashAdjusted['movement_debit'])->toBe('30.0000')
        ->and($cashAdjusted['closing_debit'])->toBe('130.0000')
        ->and($adjusted['totals']['closing_debit'])->toBe($adjusted['totals']['closing_credit']);
});

it('supports period dates as-of consistency and account hierarchy detail', function (): void {
    $context = trialBalanceContext();
    $cash = $context['accounts']['1010'];
    $capital = $context['accounts']['3010'];
    postTrialJournal($context, 'TB-ASOF-1', '2026-07-05', 'cash_receipt', [
        ['account_id' => $cash->id, 'debit' => '10.0000', 'credit' => '0.0000'],
        ['account_id' => $capital->id, 'debit' => '0.0000', 'credit' => '10.0000'],
    ]);
    postTrialJournal($context, 'TB-ASOF-2', '2026-07-20', 'cash_receipt', [
        ['account_id' => $cash->id, 'debit' => '20.0000', 'credit' => '0.0000'],
        ['account_id' => $capital->id, 'debit' => '0.0000', 'credit' => '20.0000'],
    ]);
    $assetHeader = Account::query()->where('code', '1000')->sole();
    $report = app(TrialBalanceReport::class)->generate(trialFilters([
        'end_date' => '2026-07-10', 'as_of' => '2026-07-10',
        'account_id' => $assetHeader->id, 'detail' => 'hierarchy',
    ]), false);

    expect($report['rows']->pluck('account.code')->all())->toContain('1000', '1010')
        ->and($report['rows']->firstWhere('account.id', $cash->id)['closing_debit'])->toBe('10.0000');

    $this->actingAs($context['user'])->get(route('trial-balance.index', [
        'fiscal_period_id' => $context['period']->id, 'basis' => 'adjusted', 'detail' => 'postable',
    ]))->assertSuccessful()->assertSee('2026-07-01');
});

it('reconciles AR AP cash and inventory controls to independent operational sources', function (): void {
    $context = trialBalanceContext();
    $customer = Customer::factory()->create();
    SalesInvoice::factory()->for($customer)->for($context['period'])->create([
        'invoice_number' => 'SI-RECON-1', 'invoice_date' => '2026-07-05', 'due_date' => '2026-08-05',
        'gross_amount' => '100.0000', 'net_sales_amount' => '100.0000', 'total_receivable' => '100.0000',
        'balance_due' => '100.0000', 'status' => SalesInvoiceStatus::Posted,
        'posted_at' => '2026-07-05', 'posted_by' => $context['user']->id,
    ]);
    FinancialAccount::factory()->create([
        'type' => 'cash_on_hand', 'opening_balance' => '50.0000', 'opening_balance_date' => '2026-07-01',
    ]);
    $product = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    $warehouse = Warehouse::factory()->create();
    InventoryMovement::query()->create([
        'product_service_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => 'purchase_receipt',
        'movement_date' => '2026-07-05', 'quantity' => '2.0000', 'unit_cost' => '20.0000', 'total_cost' => '40.0000',
        'balance_quantity_after' => '2.0000', 'balance_average_cost_after' => '20.0000',
        'status' => 'posted', 'posted_at' => now(), 'posted_by' => $context['user']->id, 'created_by' => $context['user']->id,
    ]);

    postTrialJournal($context, 'TB-RECON', '2026-07-05', 'opening', [
        ['account_id' => $context['accounts']['1100']->id, 'debit' => '100.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['1010']->id, 'debit' => '50.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['1200']->id, 'debit' => '40.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['2010']->id, 'debit' => '0.0000', 'credit' => '80.0000'],
        ['account_id' => $context['accounts']['3010']->id, 'debit' => '0.0000', 'credit' => '110.0000'],
    ]);

    $supplier = Supplier::factory()->create();
    SupplierInvoice::query()->create([
        'supplier_id' => $supplier->id, 'fiscal_period_id' => $context['period']->id,
        'supplier_invoice_number' => 'BILL-RECON-1', 'invoice_date' => '2026-07-05', 'due_date' => '2026-08-05',
        'supplier_name' => $supplier->name, 'gross_purchase_amount' => '80.0000', 'net_purchase_amount' => '80.0000',
        'total_payable' => '80.0000', 'balance_due' => '80.0000', 'status' => 'posted',
        'posted_at' => now(), 'posted_by' => $context['user']->id, 'created_by' => $context['user']->id, 'updated_by' => $context['user']->id,
    ]);

    $rows = app(SubledgerReconciliationReport::class)->generate(trialFilters())->keyBy('account.control_account_type');

    foreach (['accounts_receivable', 'accounts_payable', 'cash_on_hand', 'inventory'] as $type) {
        expect($rows[$type]['difference'])->toBe('0.0000');
    }
    expect($rows['percentage_tax_payable']['available'])->toBeFalse()
        ->and($rows['accounts_receivable']['drilldown'])->not->toBeNull();
});

it('shows reconciliation differences without silently changing either balance', function (): void {
    $context = trialBalanceContext();
    $customer = Customer::factory()->create();
    SalesInvoice::factory()->for($customer)->for($context['period'])->create([
        'invoice_number' => 'SI-DIFF-1', 'invoice_date' => '2026-07-05', 'due_date' => '2026-08-05',
        'gross_amount' => '100.0000', 'net_sales_amount' => '100.0000', 'total_receivable' => '100.0000',
        'balance_due' => '100.0000', 'status' => 'posted', 'posted_at' => now(), 'posted_by' => $context['user']->id,
    ]);
    postTrialJournal($context, 'TB-DIFF', '2026-07-05', 'sales', [
        ['account_id' => $context['accounts']['1100']->id, 'debit' => '90.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['3010']->id, 'debit' => '0.0000', 'credit' => '90.0000'],
    ]);

    $row = app(SubledgerReconciliationReport::class)->generate(trialFilters())
        ->firstWhere('account.control_account_type', 'accounts_receivable');

    expect($row['ledger'])->toBe('90.0000')
        ->and($row['subledger'])->toBe('100.0000')
        ->and($row['difference'])->toBe('-10.0000');
});

it('validates report dates and enforces view and export permissions', function (): void {
    $context = trialBalanceContext();
    $unauthorized = User::factory()->create();
    $viewOnly = User::factory()->create();
    $viewOnly->givePermissionTo('trial-balance.view');

    $this->actingAs($unauthorized)->get(route('trial-balance.index'))->assertForbidden();
    $this->actingAs($viewOnly)->get(route('trial-balance.index'))->assertSuccessful();
    $this->actingAs($viewOnly)->get(route('trial-balance.export'))->assertForbidden();
    $this->actingAs($context['user'])->get(route('trial-balance.index', [
        'start_date' => '2026-07-31', 'end_date' => '2026-07-01', 'as_of' => '2026-07-01',
    ]))->assertSessionHasErrors('end_date');
    $this->actingAs($context['user'])->get(route('subledger-reconciliations.export'))
        ->assertSuccessful()->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
