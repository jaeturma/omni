<?php

namespace App\Policies;

use App\Models\SourcePosting;
use App\Models\User;

class SourcePostingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('source-posting.view');
    }

    public function retry(User $user, SourcePosting $sourcePosting): bool
    {
        return $user->can('source-posting.retry') && $sourcePosting->status === 'failed';
    }

    public function rebuildLink(User $user, SourcePosting $sourcePosting): bool
    {
        return $user->can('source-posting.rebuild-link') && $sourcePosting->journal_entry_id === null;
    }
}
