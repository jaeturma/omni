<?php

namespace App\Models\Concerns;

use App\Models\SalesAttachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSalesAttachments
{
    /** @return MorphMany<SalesAttachment, $this> */
    public function salesAttachments(): MorphMany
    {
        return $this->morphMany(SalesAttachment::class, 'attachable')->latest();
    }
}
