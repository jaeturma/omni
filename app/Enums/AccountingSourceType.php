<?php

namespace App\Enums;

use App\Models\CashDisbursement;
use App\Models\CashReceipt;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\FundTransfer;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashVoucher;
use App\Models\PhysicalCount;
use App\Models\ReceivingRecord;
use App\Models\SalesInvoice;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Model;

enum AccountingSourceType: string
{
    case SalesInvoice = 'sales_invoice';
    case CustomerPayment = 'customer_payment';
    case SupplierInvoice = 'supplier_invoice';
    case SupplierPayment = 'supplier_payment';
    case Expense = 'expense';
    case CashReceipt = 'cash_receipt';
    case CashDisbursement = 'cash_disbursement';
    case FundTransfer = 'fund_transfer';
    case InventoryMovement = 'inventory_movement';
    case Delivery = 'delivery';
    case ReceivingRecord = 'receiving_record';
    case InventoryAdjustment = 'inventory_adjustment';
    case InventoryTransfer = 'inventory_transfer';
    case PhysicalCount = 'physical_count';
    case PettyCashVoucher = 'petty_cash_voucher';
    case PettyCashReplenishment = 'petty_cash_replenishment';
    case Manual = 'manual';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::SalesInvoice => SalesInvoice::class,
            self::CustomerPayment => CustomerPayment::class,
            self::SupplierInvoice => SupplierInvoice::class,
            self::SupplierPayment => SupplierPayment::class,
            self::Expense => Expense::class,
            self::CashReceipt => CashReceipt::class,
            self::CashDisbursement => CashDisbursement::class,
            self::FundTransfer => FundTransfer::class,
            self::InventoryMovement => InventoryMovement::class,
            self::Delivery => Delivery::class,
            self::ReceivingRecord => ReceivingRecord::class,
            self::InventoryAdjustment => InventoryAdjustment::class,
            self::InventoryTransfer => InventoryTransfer::class,
            self::PhysicalCount => PhysicalCount::class,
            self::PettyCashVoucher => PettyCashVoucher::class,
            self::PettyCashReplenishment => PettyCashReplenishment::class,
            self::Manual => throw new \LogicException('Manual journals do not have an operational source model.'),
        };
    }
}
