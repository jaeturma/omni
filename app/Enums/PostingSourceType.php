<?php

namespace App\Enums;

enum PostingSourceType: string
{
    case Sale = 'sale';
    case SalesReturn = 'sales_return';
    case SalesDiscount = 'sales_discount';
    case CustomerCollection = 'customer_collection';
    case CustomerWithholding = 'customer_withholding';
    case Purchase = 'purchase';
    case SupplierPayment = 'supplier_payment';
    case OperatingExpense = 'operating_expense';
    case CashReceipt = 'cash_receipt';
    case CashDisbursement = 'cash_disbursement';
    case BankCharge = 'bank_charge';
    case Transfer = 'transfer';
    case InventoryReceipt = 'inventory_receipt';
    case InventoryIssue = 'inventory_issue';
    case InventoryAdjustment = 'inventory_adjustment';
    case PhysicalCountGain = 'physical_count_gain';
    case PhysicalCountLoss = 'physical_count_loss';
    case OwnerCapital = 'owner_capital';
    case OwnerDrawings = 'owner_drawings';

    /** @return array{debit: string, credit: string} */
    public function roles(): array
    {
        return match ($this) {
            self::Sale => ['debit' => 'Accounts receivable or cash', 'credit' => 'Sales income'],
            self::SalesReturn => ['debit' => 'Sales returns', 'credit' => 'Accounts receivable or cash'],
            self::SalesDiscount => ['debit' => 'Sales discounts', 'credit' => 'Accounts receivable'],
            self::CustomerCollection => ['debit' => 'Cash or bank', 'credit' => 'Accounts receivable'],
            self::CustomerWithholding => ['debit' => 'Creditable withholding tax', 'credit' => 'Accounts receivable'],
            self::Purchase => ['debit' => 'Inventory or purchases', 'credit' => 'Accounts payable or cash'],
            self::SupplierPayment => ['debit' => 'Accounts payable', 'credit' => 'Cash or bank'],
            self::OperatingExpense => ['debit' => 'Operating expense', 'credit' => 'Accounts payable or cash'],
            self::CashReceipt => ['debit' => 'Cash or bank', 'credit' => 'Receipt source'],
            self::CashDisbursement => ['debit' => 'Disbursement purpose', 'credit' => 'Cash or bank'],
            self::BankCharge => ['debit' => 'Bank charges', 'credit' => 'Cash at bank'],
            self::Transfer => ['debit' => 'Destination financial account', 'credit' => 'Source financial account'],
            self::InventoryReceipt => ['debit' => 'Inventory', 'credit' => 'Inventory receipt clearing'],
            self::InventoryIssue => ['debit' => 'Cost or expense', 'credit' => 'Inventory'],
            self::InventoryAdjustment => ['debit' => 'Adjustment debit', 'credit' => 'Adjustment credit'],
            self::PhysicalCountGain => ['debit' => 'Inventory', 'credit' => 'Inventory gain'],
            self::PhysicalCountLoss => ['debit' => 'Inventory loss', 'credit' => 'Inventory'],
            self::OwnerCapital => ['debit' => 'Cash or contributed asset', 'credit' => 'Owner capital'],
            self::OwnerDrawings => ['debit' => 'Owner drawings', 'credit' => 'Cash or withdrawn asset'],
        };
    }
}
