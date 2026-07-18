<?php

namespace App\Policies;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use App\Models\User;

class SupplierInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplier-invoices.view');
    }

    public function view(User $user, SupplierInvoice $invoice): bool
    {
        return $user->can('supplier-invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplier-invoices.create');
    }

    public function update(User $user, SupplierInvoice $invoice): bool
    {
        return $invoice->status === SupplierInvoiceStatus::Draft && $user->can('supplier-invoices.update');
    }

    public function delete(User $user, SupplierInvoice $invoice): bool
    {
        return $invoice->status === SupplierInvoiceStatus::Draft && $user->can('supplier-invoices.update');
    }

    public function post(User $user, SupplierInvoice $invoice): bool
    {
        return $invoice->status === SupplierInvoiceStatus::Draft && $user->can('supplier-invoices.post');
    }

    public function void(User $user, SupplierInvoice $invoice): bool
    {
        return in_array($invoice->status, [SupplierInvoiceStatus::Posted, SupplierInvoiceStatus::Overdue], true)
            && bccomp($invoice->paid_amount, '0', 4) === 0
            && $user->can('supplier-invoices.void');
    }

    public function print(User $user, SupplierInvoice $invoice): bool
    {
        return $user->can('supplier-invoices.print');
    }
}
