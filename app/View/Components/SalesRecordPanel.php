<?php

namespace App\View\Components;

use App\Services\SalesAttachmentManager;
use App\Services\SalesTraceability;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

class SalesRecordPanel extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public Model $record, SalesTraceability $traceability, SalesAttachmentManager $manager)
    {
        $this->record->loadMissing('salesAttachments.uploader');
        $this->links = $traceability->links($record);
        $this->deletionProtected = $manager->isProtected($record);
    }

    public $links;

    public bool $deletionProtected;

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.sales-record-panel');
    }
}
