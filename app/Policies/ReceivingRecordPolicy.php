<?php

namespace App\Policies;

use App\Enums\ReceivingStatus;
use App\Models\ReceivingRecord;
use App\Models\User;

class ReceivingRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('receiving-records.view');
    }

    public function view(User $user, ReceivingRecord $record): bool
    {
        return $user->can('receiving-records.view');
    }

    public function create(User $user): bool
    {
        return $user->can('receiving-records.create');
    }

    public function delete(User $user, ReceivingRecord $record): bool
    {
        return $record->status === ReceivingStatus::Draft && $user->can('receiving-records.cancel');
    }

    public function receive(User $user, ReceivingRecord $record): bool
    {
        return $user->can('receiving-records.create');
    }

    public function inspect(User $user, ReceivingRecord $record): bool
    {
        return $user->can('receiving-records.inspect');
    }

    public function accept(User $user, ReceivingRecord $record): bool
    {
        return $user->can('receiving-records.accept');
    }

    public function cancel(User $user, ReceivingRecord $record): bool
    {
        return $user->can('receiving-records.cancel');
    }

    public function print(User $user, ReceivingRecord $record): bool
    {
        return $user->can('receiving-records.print');
    }
}
