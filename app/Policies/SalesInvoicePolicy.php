<?php

namespace App\Policies;

use App\Enums\SalesInvoiceStatus;
use App\Models\SalesInvoice;
use App\Models\User;

class SalesInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales-invoices.view');
    }

    public function view(User $user, SalesInvoice $invoice): bool
    {
        return $user->can('sales-invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales-invoices.create');
    }

    public function update(User $user, SalesInvoice $invoice): bool
    {
        return $user->can('sales-invoices.update') && $invoice->status === SalesInvoiceStatus::Draft;
    }

    public function post(User $user, SalesInvoice $invoice): bool
    {
        return $user->can('sales-invoices.post');
    }

    public function void(User $user, SalesInvoice $invoice): bool
    {
        return $user->can('sales-invoices.void');
    }

    public function print(User $user, SalesInvoice $invoice): bool
    {
        return $user->can('sales-invoices.print');
    }
}
