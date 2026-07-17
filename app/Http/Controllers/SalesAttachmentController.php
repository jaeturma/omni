<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteSalesAttachmentRequest;
use App\Http\Requests\StoreSalesAttachmentRequest;
use App\Models\SalesAttachment;
use App\Services\SalesAttachmentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesAttachmentController extends Controller
{
    public function store(StoreSalesAttachmentRequest $request, string $attachableType, int $attachableId, SalesAttachmentManager $manager): RedirectResponse
    {
        $record = $manager->resolve($attachableType, $attachableId);
        Gate::authorize('view', $record);
        $manager->store($record, $request->file('file'), $request->safe()->except('file'), $request->user());

        return back()->with('success', 'Attachment uploaded.');
    }

    public function download(SalesAttachment $salesAttachment): StreamedResponse
    {
        Gate::authorize('view', $salesAttachment);
        abort_unless(Storage::disk('local')->exists($salesAttachment->stored_filename), 404);

        return Storage::disk('local')->download($salesAttachment->stored_filename, $salesAttachment->original_filename, ['Content-Type' => $salesAttachment->mime_type]);
    }

    public function destroy(DeleteSalesAttachmentRequest $request, SalesAttachment $salesAttachment, SalesAttachmentManager $manager): RedirectResponse
    {
        Gate::authorize('delete', $salesAttachment);
        $manager->delete($salesAttachment, $request->user(), $request->string('deletion_reason')->toString());

        return back()->with('success', 'Attachment deleted.');
    }
}
