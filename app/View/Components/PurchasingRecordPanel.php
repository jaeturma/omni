<?php

namespace App\View\Components;

use App\Models\CanvassQuotation;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\ReceivingRecord;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\PurchasingTraceability;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PurchasingRecordPanel extends Component
{
    public function __construct(public PurchaseRequest|CanvassQuotation|PurchaseOrder|ReceivingRecord|SupplierInvoice|SupplierPayment|Expense $record, public string $type, private PurchasingTraceability $traceability) {}

    public function render(): View|Closure|string
    {
        $this->record->loadMissing('purchasingAttachments.uploader');

        return view('components.purchasing-record-panel', ['links' => $this->traceability->links($this->record)]);
    }
}
