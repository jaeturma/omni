<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case OpeningBalance = 'opening_balance';
    case PurchaseReceipt = 'purchase_receipt';
    case SalesIssue = 'sales_issue';
    case CustomerReturn = 'customer_return';
    case SupplierReturn = 'supplier_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case PhysicalCountGain = 'physical_count_gain';
    case PhysicalCountLoss = 'physical_count_loss';
}
