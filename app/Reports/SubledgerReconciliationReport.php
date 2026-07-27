<?php

namespace App\Reports;

use App\Enums\CashTransactionStatus;
use App\Enums\CustomerPaymentStatus;
use App\Enums\ExpenseStatus;
use App\Enums\FinancialAccountType;
use App\Enums\InventoryMovementStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\InventoryMovement;
use App\Models\JournalEntryLine;
use App\Models\SalesInvoice;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\BankReconciliationCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SubledgerReconciliationReport
{
    public function __construct(
        private BankReconciliationCalculator $cashCalculator,
        private InventoryStockReport $inventoryReport,
    ) {}

    /** @param array<string, mixed> $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function generate(array $filters): Collection
    {
        $asOf = Carbon::parse($filters['as_of']);

        return Account::query()->where('is_control_account', true)->ordered()
            ->get(['id', 'code', 'name', 'normal_balance', 'control_account_type'])
            ->map(fn (Account $account): array => $this->row($account, $asOf));
    }

    /** @return array<string, mixed> */
    private function row(Account $account, Carbon $asOf): array
    {
        $ledger = $this->ledgerBalance($account, $asOf);
        [$subledger, $count, $drilldown] = match ($account->control_account_type) {
            'accounts_receivable' => $this->receivables($asOf),
            'accounts_payable' => $this->payables($asOf),
            'cash_on_hand' => $this->cash($asOf, [FinancialAccountType::CashOnHand]),
            'petty_cash' => $this->cash($asOf, [FinancialAccountType::PettyCash]),
            'cash_in_bank' => $this->cash($asOf, [FinancialAccountType::BankChecking, FinancialAccountType::BankSavings, FinancialAccountType::ClearingAccount, FinancialAccountType::OtherCashEquivalent]),
            'e_wallet' => $this->cash($asOf, [FinancialAccountType::EWallet]),
            'inventory' => $this->inventory($asOf),
            'creditable_withholding_tax' => $this->creditableWithholding($asOf),
            'withholding_tax_payable' => $this->withholdingPayable($asOf),
            default => [null, 0, null],
        };

        return [
            'account' => $account,
            'ledger' => $ledger,
            'subledger' => $subledger,
            'difference' => $subledger === null ? null : bcsub($ledger, $subledger, 4),
            'source_count' => $count,
            'drilldown' => $drilldown,
            'available' => $subledger !== null,
        ];
    }

    private function ledgerBalance(Account $account, Carbon $asOf): string
    {
        $totals = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted, JournalEntryStatus::Reversed])
            ->whereDate('journal_entries.journal_date', '<=', $asOf)
            ->toBase()->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit, COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
            ->first();
        $debit = bcadd('0', (string) $totals->debit, 4);
        $credit = bcadd('0', (string) $totals->credit, 4);

        return $account->normal_balance->value === 'credit'
            ? bcsub($credit, $debit, 4)
            : bcsub($debit, $credit, 4);
    }

    /** @return array{string, int, string} */
    private function receivables(Carbon $asOf): array
    {
        $invoices = SalesInvoice::query()->whereDate('invoice_date', '<=', $asOf)
            ->whereNotIn('status', [SalesInvoiceStatus::Draft, SalesInvoiceStatus::Voided]);
        $payments = CustomerPayment::query()->whereDate('payment_date', '<=', $asOf)
            ->whereNotIn('status', [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided]);
        $balance = bcsub($this->sum($invoices, 'net_sales_amount'), $this->sum($payments, 'gross_settlement_amount'), 4);

        return [$balance, $invoices->count() + $payments->count(), route('receivables.index', ['as_of' => $asOf->toDateString()])];
    }

    /** @return array{string, int, string} */
    private function payables(Carbon $asOf): array
    {
        $invoices = SupplierInvoice::query()->whereDate('invoice_date', '<=', $asOf)
            ->whereNotIn('status', [SupplierInvoiceStatus::Draft, SupplierInvoiceStatus::Voided]);
        $payments = SupplierPayment::query()->whereDate('payment_date', '<=', $asOf)
            ->whereNotIn('status', [SupplierPaymentStatus::Draft, SupplierPaymentStatus::Voided]);
        $balance = bcsub($this->sum($invoices, 'total_payable'), $this->sum($payments, 'gross_settlement_amount'), 4);

        return [$balance, $invoices->count() + $payments->count(), route('payables.index', ['as_of' => $asOf->toDateString()])];
    }

    /** @param list<FinancialAccountType> $types
     * @return array{string, int, string}
     */
    private function cash(Carbon $asOf, array $types): array
    {
        $accounts = FinancialAccount::query()->whereIn('type', $types)->get(['id', 'opening_balance', 'opening_balance_date']);
        $balance = '0.0000';
        foreach ($accounts as $financialAccount) {
            if ($financialAccount->opening_balance_date === null || $financialAccount->opening_balance_date->lte($asOf)) {
                $balance = bcadd($balance, $financialAccount->opening_balance, 4);
            }
        }
        $transactions = CashTransaction::query()->whereIn('financial_account_id', $accounts->pluck('id'))
            ->where('status', CashTransactionStatus::Posted)->whereDate('transaction_date', '<=', $asOf);
        foreach ((clone $transactions)->cursor() as $transaction) {
            $balance = bcadd($balance, $this->cashCalculator->signed($transaction), 4);
        }

        return [$balance, $transactions->count(), route('cash-reports.index', ['as_of' => $asOf->toDateString(), 'end_date' => $asOf->toDateString()])];
    }

    /** @return array{string, int, string} */
    private function inventory(Carbon $asOf): array
    {
        $filters = ['as_of' => $asOf->toDateString(), 'start_date' => '1900-01-01', 'end_date' => $asOf->toDateString(),
            'product_service_id' => null, 'warehouse_id' => null, 'category_id' => null, 'brand_id' => null, 'movement_type' => null];
        $stocks = $this->inventoryReport->stockRows($filters);
        $value = $stocks->reduce(fn (string $total, InventoryMovement $stock): string => bcadd(
            $total, bcmul($stock->quantity, (string) ($stock->as_of_average_cost ?? '0.0000'), 4), 4
        ), '0.0000');
        $count = InventoryMovement::query()->where('status', InventoryMovementStatus::Posted)
            ->whereDate('movement_date', '<=', $asOf)->count();

        return [$value, $count, route('inventory-reports.index', ['as_of' => $asOf->toDateString(), 'end_date' => $asOf->toDateString()])];
    }

    /** @return array{string, int, string} */
    private function creditableWithholding(Carbon $asOf): array
    {
        $payments = CustomerPayment::query()->whereDate('payment_date', '<=', $asOf)
            ->whereNotIn('status', [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided]);

        return [$this->sum($payments, 'withholding_amount'), $payments->where('withholding_amount', '>', 0)->count(),
            route('government-deductions.index', ['year' => $asOf->year])];
    }

    /** @return array{string, int, string} */
    private function withholdingPayable(Carbon $asOf): array
    {
        $supplierInvoices = SupplierInvoice::query()->whereDate('invoice_date', '<=', $asOf)
            ->whereNotIn('status', [SupplierInvoiceStatus::Draft, SupplierInvoiceStatus::Voided]);
        $supplierPayments = SupplierPayment::query()->whereDate('payment_date', '<=', $asOf)
            ->whereNotIn('status', [SupplierPaymentStatus::Draft, SupplierPaymentStatus::Voided]);
        $expenses = Expense::query()->whereDate('expense_date', '<=', $asOf)
            ->whereNotIn('status', [ExpenseStatus::Draft, ExpenseStatus::Voided]);
        $balance = bcadd(
            bcadd($this->sum($supplierInvoices, 'withholding_expected_amount'), $this->sum($supplierPayments, 'withholding_amount'), 4),
            $this->sum($expenses, 'withholding_amount'),
            4,
        );
        $count = $supplierInvoices->where('withholding_expected_amount', '>', 0)->count()
            + $supplierPayments->where('withholding_amount', '>', 0)->count()
            + $expenses->where('withholding_amount', '>', 0)->count();

        return [$balance, $count, route('payables.index', ['as_of' => $asOf->toDateString()])];
    }

    private function sum(mixed $query, string $column): string
    {
        return bcadd('0', (string) $query->sum($column), 4);
    }
}
