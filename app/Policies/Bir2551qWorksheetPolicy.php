<?php

namespace App\Policies;

use App\Models\Bir2551qWorksheet;
use App\Models\User;

class Bir2551qWorksheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bir-2551q.view');
    }

    public function view(User $user, Bir2551qWorksheet $worksheet): bool
    {
        return $user->can('bir-2551q.view');
    }

    public function update(User $user, Bir2551qWorksheet $worksheet): bool
    {
        return $user->can('bir-2551q.prepare') && $worksheet->status === 'draft';
    }

    public function review(User $user, Bir2551qWorksheet $worksheet): bool
    {
        return $user->can('bir-2551q.review');
    }

    public function approve(User $user, Bir2551qWorksheet $worksheet): bool
    {
        return $user->can('bir-2551q.approve');
    }

    public function revise(User $user, Bir2551qWorksheet $worksheet): bool
    {
        return $user->can('bir-2551q.revise') && $worksheet->frozen_at !== null;
    }

    public function export(User $user, Bir2551qWorksheet $worksheet): bool
    {
        return $user->can('bir-2551q.export');
    }

    public function delete(User $user, Bir2551qWorksheet $worksheet): bool
    {
        return false;
    }
}
