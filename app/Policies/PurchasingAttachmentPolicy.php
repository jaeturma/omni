<?php

namespace App\Policies;

use App\Models\CanvassQuotation;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchasingAttachment;
use App\Models\ReceivingRecord;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\PurchasingAttachmentManager;
use Illuminate\Support\Facades\Gate;

class PurchasingAttachmentPolicy
{
    public function __construct(private readonly PurchasingAttachmentManager $manager) {}

    public function view(User $user, PurchasingAttachment $attachment): bool
    {
        return $user->can('purchasing-attachments.view')
            && Gate::forUser($user)->allows('view', $attachment->attachable);
    }

    public function create(User $user): bool
    {
        return $user->can('purchasing-attachments.upload');
    }

    public function delete(User $user, PurchasingAttachment $attachment): bool
    {
        $record = $attachment->attachable;
        if (! $record instanceof PurchaseRequest && ! $record instanceof CanvassQuotation && ! $record instanceof PurchaseOrder
            && ! $record instanceof ReceivingRecord && ! $record instanceof SupplierInvoice && ! $record instanceof SupplierPayment && ! $record instanceof Expense) {
            return false;
        }

        return $user->can('purchasing-attachments.delete')
            && Gate::forUser($user)->allows('view', $record)
            && ! $this->manager->isProtected($record);
    }
}
