<?php

namespace App\Models\Concerns;

use App\Models\PurchasingAttachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPurchasingAttachments
{
    /** @return MorphMany<PurchasingAttachment, $this> */
    public function purchasingAttachments(): MorphMany
    {
        return $this->morphMany(PurchasingAttachment::class, 'attachable')->latest();
    }
}
