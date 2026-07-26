<?php

namespace App\Observers;

use App\Services\AutomaticSourcePosting;
use Illuminate\Database\Eloquent\Model;

class AutomaticSourcePostingObserver
{
    public function __construct(private AutomaticSourcePosting $posting) {}

    public function created(Model $source): void
    {
        if ($this->posting->shouldPost($source, true)) {
            $this->posting->attempt($source, $this->posting->userId($source));
        }
    }

    public function updated(Model $source): void
    {
        if ($this->posting->shouldPost($source)) {
            $this->posting->attempt($source, $this->posting->userId($source));
        }
    }
}
