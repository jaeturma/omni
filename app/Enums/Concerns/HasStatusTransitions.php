<?php

namespace App\Enums\Concerns;

trait HasStatusTransitions
{
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
