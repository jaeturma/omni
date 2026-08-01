<?php

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryLine;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Reports\ManagementProfitabilityReport;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function managementContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $year = FiscalYear::factory()->create(['name' => 'FY 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_current' => true]);
    $period = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'June 2026', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-30',
        'calendar_year' => 2026, 'calendar_month' => 6, 'calendar_quarter' => 2, 'status' => 'open',
    ]);
    $accounts = Account::query()->whereIn('code', ['1010', '4010', '5010', '6010'])->get()->keyBy('code');

    return compact('user', 'period', 'accounts');
}

function managementFilters(array $changes = []): array
{
    return array_replace([
        'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'customer_id' => null,
        'category_id' => null, 'report' => 'sales',
    ], $changes);
}

function postManagementJournal(array $context, string $sourceType, int $sourceId, string $number, array $lines): void
{
    $entry = app(SaveJournalEntry::class)->handle([
        'journal_number' => $number, 'journal_date' => '2026-06-15', 'document_date' => '2026-06-15',
        'fiscal_period_id' => $context['period']->id, 'journal_type' => $sourceType === 'expense' ? 'expense' : 'sales',
        'source_type' => $sourceType, 'source_id' => $sourceId, 'reference_number' => $number, 'description' => $number, 'lines' => $lines,
    ], $context['user']->id);
    app(TransitionJournalEntry::class)->handle($entry, JournalEntryStatus::Posted, $context['user']->id);
}

function createManagementSale(array $context, Customer $customer, ProductService $product, string $amount, string $number): SalesInvoice
{
    $invoice = SalesInvoice::factory()->create([
        'customer_id' => $customer->id, 'fiscal_period_id' => $context['period']->id, 'invoice_number' => $number,
        'invoice_date' => '2026-06-15', 'due_date' => '2026-07-15', 'gross_amount' => $amount,
        'net_sales_amount' => $amount, 'total_receivable' => $amount, 'balance_due' => $amount, 'status' => 'posted',
    ]);
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id, 'product_service_id' => $product->id, 'item_type' => $product->type,
        'description' => $product->name, 'gross_amount' => $amount, 'net_amount' => $amount, 'unit_price' => $amount,
    ]);
    postManagementJournal($context, 'sales_invoice', $invoice->id, 'J-'.$number, [
        ['account_id' => $context['accounts']['1010']->id, 'debit' => $amount, 'credit' => '0.0000', 'customer_id' => $customer->id],
        ['account_id' => $context['accounts']['4010']->id, 'debit' => '0.0000', 'credit' => $amount, 'customer_id' => $customer->id, 'product_id' => $product->id],
    ]);

    return $invoice;
}

it('reconciles sales dimensions and government private splits without double counting', function (): void {
    $context = managementContext();
    $category = Category::factory()->create(['name' => 'ICT Equipment']);
    $product = ProductService::factory()->create(['category_id' => $category->id, 'name' => 'Laptop']);
    $government = Customer::factory()->create(['name' => 'DepEd Office', 'type' => 'government']);
    $private = Customer::factory()->create(['name' => 'Private School', 'type' => 'private']);
    createManagementSale($context, $government, $product, '1000.0000', 'SI-001');
    createManagementSale($context, $private, $product, '500.0000', 'SI-002');

    $report = app(ManagementProfitabilityReport::class)->generate(managementFilters(), true);
    $market = $report['sections']->firstWhere('label', 'Sales by Customer Type / Market')['rows'];
    $reconciliation = $report['sections']->firstWhere('label', 'Ledger Reconciliation')['rows'];

    expect($market->firstWhere('label', 'Government')['amount'])->toBe('1000.0000')
        ->and($market->firstWhere('label', 'Private')['amount'])->toBe('500.0000')
        ->and($reconciliation->firstWhere('label', 'Operational posted sales')['amount'])->toBe('1500.0000')
        ->and($reconciliation->firstWhere('label', 'Income statement net sales')['amount'])->toBe('1500.0000')
        ->and($reconciliation->firstWhere('label', 'Unattributed ledger / filtered difference')['amount'])->toBe('0.0000');
});

it('applies customer and category filters to operational dimensions', function (): void {
    $context = managementContext();
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $customer = Customer::factory()->create();
    createManagementSale($context, $customer, ProductService::factory()->create(['category_id' => $category->id]), '300.0000', 'SI-FILTER');
    createManagementSale($context, Customer::factory()->create(), ProductService::factory()->create(['category_id' => $otherCategory->id]), '700.0000', 'SI-OTHER');

    $report = app(ManagementProfitabilityReport::class)->generate(managementFilters([
        'customer_id' => $customer->id, 'category_id' => $category->id,
    ]), true);
    $rows = $report['sections']->firstWhere('label', 'Sales by Customer')['rows'];

    expect($rows)->toHaveCount(1)->and($rows->first()['amount'])->toBe('300.0000');
});

it('calculates inventory gross profit and margin from delivery movement cost', function (): void {
    $context = managementContext();
    $product = ProductService::factory()->create(['name' => 'Router']);
    $invoice = createManagementSale($context, Customer::factory()->create(), $product, '1000.0000', 'SI-GP');
    $deliveryLine = DeliveryLine::factory()->create();
    SalesInvoiceLine::query()->where('sales_invoice_id', $invoice->id)->update(['delivery_line_id' => $deliveryLine->id]);
    InventoryMovement::query()->create([
        'delivery_line_id' => $deliveryLine->id, 'product_service_id' => $product->id, 'warehouse_id' => Warehouse::factory()->create()->id,
        'type' => 'sales_issue', 'movement_date' => '2026-06-15', 'quantity' => '-1.0000', 'unit_cost' => '600.0000',
        'total_cost' => '-600.0000', 'status' => 'posted', 'posted_at' => now(), 'posted_by' => $context['user']->id, 'created_by' => $context['user']->id,
    ]);

    $report = app(ManagementProfitabilityReport::class)->generate(managementFilters(['report' => 'profitability']), true);
    $section = $report['sections']->first();
    $row = $section['rows']->first();
    $reconciliation = $report['sections']->firstWhere('label', 'Ledger Reconciliation')['rows'];

    expect($row['sales'])->toBe('1000.0000')->and($row['cost'])->toBe('600.0000')
        ->and($row['profit'])->toBe('400.0000')->and($row['margin'])->toBe('40.00')
        ->and($reconciliation->firstWhere('label', 'Operational dimensions')['profit'])->toBe('400.0000');
});

it('reconciles operational expenses and protects cost reports through permissions', function (): void {
    $context = managementContext();
    $expense = Expense::query()->create([
        'fiscal_period_id' => $context['period']->id, 'expense_number' => 'EXP-001', 'expense_date' => '2026-06-15',
        'payee_name' => 'Utility Provider', 'expense_category' => 'utilities', 'description' => 'Power', 'business_purpose' => 'Operations',
        'gross_amount' => '250.0000', 'net_cash_paid' => '250.0000', 'status' => 'paid', 'created_by' => $context['user']->id, 'updated_by' => $context['user']->id,
    ]);
    postManagementJournal($context, 'expense', $expense->id, 'J-EXP-001', [
        ['account_id' => $context['accounts']['6010']->id, 'debit' => '250.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['1010']->id, 'debit' => '0.0000', 'credit' => '250.0000'],
    ]);
    $expenseReport = app(ManagementProfitabilityReport::class)->generate(managementFilters(['report' => 'expenses']), true);
    $reconciliation = $expenseReport['sections']->firstWhere('label', 'Ledger Reconciliation')['rows'];

    expect($reconciliation->firstWhere('label', 'Unattributed ledger difference')['amount'])->toBe('0.0000');

    $this->actingAs($context['user'])->get(route('management-reports.print', managementFilters(['report' => 'expenses'])))
        ->assertSuccessful()->assertSee('Expense Analysis');
    $this->actingAs($context['user'])->get(route('management-reports.export', managementFilters(['report' => 'expenses'])))
        ->assertSuccessful()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('management-reports.index', managementFilters(['report' => 'profitability'])))
        ->assertSuccessful()->assertSee('Restricted')->assertDontSee('250.0000');
    $this->actingAs(User::factory()->create())->get(route('management-reports.index', managementFilters()))->assertForbidden();
    $this->actingAs($viewer)->get(route('management-reports.export', managementFilters()))->assertForbidden();
    $this->actingAs($context['user'])->get(route('management-reports.index', managementFilters(['end_date' => '2028-07-01'])))
        ->assertSessionHasErrors('end_date');
});
