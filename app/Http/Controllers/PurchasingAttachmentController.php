<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePurchasingAttachmentRequest;
use App\Http\Requests\StorePurchasingAttachmentRequest;
use App\Models\PurchasingAttachment;
use App\Services\PurchasingAttachmentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchasingAttachmentController extends Controller
{
    public function store(StorePurchasingAttachmentRequest $request, string $attachableType, int $attachableId, PurchasingAttachmentManager $manager): RedirectResponse
    {
        $record = $manager->resolve($attachableType, $attachableId);
        Gate::authorize('view', $record);
        $manager->store($record, $request->file('file'), $request->safe()->except('file'), $request->user());

        return back()->with('success', 'Attachment uploaded.');
    }

    public function download(PurchasingAttachment $purchasingAttachment): StreamedResponse
    {
        Gate::authorize('view', $purchasingAttachment);
        abort_unless(Storage::disk('local')->exists($purchasingAttachment->stored_filename), 404);

        return Storage::disk('local')->download($purchasingAttachment->stored_filename, $purchasingAttachment->original_filename, ['Content-Type' => $purchasingAttachment->mime_type]);
    }

    public function destroy(DeletePurchasingAttachmentRequest $request, PurchasingAttachment $purchasingAttachment, PurchasingAttachmentManager $manager): RedirectResponse
    {
        Gate::authorize('delete', $purchasingAttachment);
        $manager->delete($purchasingAttachment, $request->user(), $request->string('deletion_reason')->toString());

        return back()->with('success', 'Attachment deleted.');
    }
}
