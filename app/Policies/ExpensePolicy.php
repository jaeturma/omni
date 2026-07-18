<?php

namespace App\Policies;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $expense->status === ExpenseStatus::Draft && $user->can('expenses.update');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $expense->status === ExpenseStatus::Draft && $user->can('expenses.update');
    }

    public function approve(User $user, Expense $expense): bool
    {
        return in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Approved], true) && $user->can('expenses.approve');
    }

    public function pay(User $user, Expense $expense): bool
    {
        return in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Approved, ExpenseStatus::Reimbursable], true) && $user->can('expenses.pay');
    }

    public function void(User $user, Expense $expense): bool
    {
        return $expense->status !== ExpenseStatus::Voided && $user->can('expenses.void');
    }

    public function print(User $user, Expense $expense): bool
    {
        return $user->can('expenses.print');
    }
}
