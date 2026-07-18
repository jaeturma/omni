<?php

namespace App\Policies;

use App\Enums\CashReceiptStatus;
use App\Models\CashReceipt;
use App\Models\User;

class CashReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cash-receipts.view');
    }

    public function view(User $user, CashReceipt $cashReceipt): bool
    {
        return $user->can('cash-receipts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cash-receipts.create');
    }

    public function update(User $user, CashReceipt $cashReceipt): bool
    {
        return $user->can('cash-receipts.update') && $cashReceipt->status === CashReceiptStatus::Draft;
    }

    public function delete(User $user, CashReceipt $cashReceipt): bool
    {
        return false;
    }

    public function post(User $user, CashReceipt $cashReceipt): bool
    {
        return $user->can('cash-receipts.post');
    }

    public function clear(User $user, CashReceipt $cashReceipt): bool
    {
        return $user->can('cash-receipts.clear');
    }

    public function void(User $user, CashReceipt $cashReceipt): bool
    {
        return $user->can('cash-receipts.void');
    }

    public function print(User $user, CashReceipt $cashReceipt): bool
    {
        return $user->can('cash-receipts.print');
    }
}
