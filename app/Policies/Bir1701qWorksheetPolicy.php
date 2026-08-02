<?php

namespace App\Policies;

use App\Models\Bir1701qWorksheet;
use App\Models\User;

class Bir1701qWorksheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bir-1701q.view');
    }

    public function view(User $user, Bir1701qWorksheet $worksheet): bool
    {
        return $user->can('bir-1701q.view');
    }

    public function update(User $user, Bir1701qWorksheet $worksheet): bool
    {
        return $user->can('bir-1701q.prepare') && $worksheet->status === 'draft';
    }

    public function review(User $user, Bir1701qWorksheet $worksheet): bool
    {
        return $user->can('bir-1701q.review');
    }

    public function approve(User $user, Bir1701qWorksheet $worksheet): bool
    {
        return $user->can('bir-1701q.approve');
    }

    public function revise(User $user, Bir1701qWorksheet $worksheet): bool
    {
        return $user->can('bir-1701q.revise') && $worksheet->frozen_at !== null;
    }

    public function export(User $user, Bir1701qWorksheet $worksheet): bool
    {
        return $user->can('bir-1701q.export');
    }

    public function delete(User $user, Bir1701qWorksheet $worksheet): bool
    {
        return false;
    }
}
