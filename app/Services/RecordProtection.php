<?php

namespace App\Services;

use App\Models\CanvassQuotation;
use App\Models\CashReceipt;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\GovernmentDeduction;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\RecordArchive;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;

class RecordProtection
{
    public function customerHasHistory(Customer $customer): bool
    {
        return RecordArchive::query()->where(['subject_type' => $customer->getMorphClass(), 'subject_id' => $customer->id])->exists()
            || Quotation::query()->whereBelongsTo($customer)->exists()
            || SalesOrder::query()->whereBelongsTo($customer)->exists()
            || Delivery::query()->whereBelongsTo($customer)->exists()
            || SalesInvoice::query()->whereBelongsTo($customer)->exists()
            || CustomerPayment::query()->whereBelongsTo($customer)->exists()
            || GovernmentDeduction::query()->whereBelongsTo($customer)->exists()
            || CashReceipt::query()->whereBelongsTo($customer)->exists();
    }

    public function supplierHasHistory(Supplier $supplier): bool
    {
        return RecordArchive::query()->where(['subject_type' => $supplier->getMorphClass(), 'subject_id' => $supplier->id])->exists()
            || CanvassQuotation::query()->whereBelongsTo($supplier)->exists()
            || PurchaseOrder::query()->whereBelongsTo($supplier)->exists()
            || SupplierInvoice::query()->whereBelongsTo($supplier)->exists()
            || SupplierPayment::query()->whereBelongsTo($supplier)->exists();
    }
}
