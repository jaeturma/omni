<?php

namespace App\Policies;

use App\Models\PostingRule;
use App\Models\User;

class PostingRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('posting-rules.view');
    }

    public function create(User $user): bool
    {
        return $user->can('posting-rules.create');
    }

    public function update(User $user, PostingRule $postingRule): bool
    {
        return $user->can('posting-rules.update');
    }

    public function activate(User $user, PostingRule $postingRule): bool
    {
        return $user->can('posting-rules.activate');
    }

    public function deactivate(User $user, PostingRule $postingRule): bool
    {
        return $user->can('posting-rules.deactivate');
    }

    public function preview(User $user): bool
    {
        return $user->can('posting-rules.preview');
    }
}
