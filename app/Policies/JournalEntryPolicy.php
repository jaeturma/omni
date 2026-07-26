<?php

namespace App\Policies;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('journals.view');
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $user->can('journals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('journals.create')
            && ($user->can('journals.adjust') || $user->can('journals.opening-entry'));
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        return $user->can('journals.update') && $entry->getRawOriginal('status') === JournalEntryStatus::Draft->value;
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $user->can('journals.post') && $entry->getRawOriginal('status') === JournalEntryStatus::Draft->value;
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $user->can('journals.reverse')
            && $entry->getRawOriginal('status') === JournalEntryStatus::Posted->value
            && $entry->reversal_entry_id === null;
    }

    public function correct(User $user, JournalEntry $entry): bool
    {
        return $user->can('journals.adjust')
            && $entry->getRawOriginal('status') === JournalEntryStatus::Posted->value
            && $entry->reversal_entry_id === null;
    }

    public function autoReverse(User $user, JournalEntry $entry): bool
    {
        return $user->can('journals.auto-reverse') && $this->reverse($user, $entry);
    }

    public function createOpening(User $user): bool
    {
        return $user->can('journals.opening-entry');
    }

    public function createAdjustment(User $user): bool
    {
        return $user->can('journals.adjust');
    }

    public function void(User $user, JournalEntry $entry): bool
    {
        return $user->can('journals.void') && in_array($entry->getRawOriginal('status'), [JournalEntryStatus::Draft->value, JournalEntryStatus::Posted->value], true);
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return false;
    }
}
