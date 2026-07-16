<?php

namespace App\Http\Controllers;

use App\Actions\IssueDocumentNumber;
use App\Http\Requests\UpsertDocumentSequenceRequest;
use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentSequenceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', DocumentSequence::class);

        return view('document-sequences.index', [
            'sequences' => DocumentSequence::query()->with(['fiscalYear', 'reservations' => fn ($query) => $query->limit(5)])->latest()->paginate(10),
            'fiscalYears' => FiscalYear::query()->latest('starts_on')->get(),
        ]);
    }

    public function store(UpsertDocumentSequenceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['fiscal_year_scope'] = $data['fiscal_year_id'] ?? 0;
        DocumentSequence::query()->create($data + ['business_profile_id' => BusinessProfile::active()->firstOrFail()->id, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Document sequence created.');
    }

    public function update(UpsertDocumentSequenceRequest $request, DocumentSequence $documentSequence): RedirectResponse
    {
        $data = $request->validated();
        $data['fiscal_year_scope'] = $data['fiscal_year_id'] ?? 0;
        $documentSequence->update($data + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Document sequence updated.');
    }

    public function issue(DocumentSequence $documentSequence, IssueDocumentNumber $issue): RedirectResponse
    {
        Gate::authorize('issue', $documentSequence);
        try {
            $reservation = $issue->handle($documentSequence, (int) auth()->id());
        } catch (DomainException $exception) {
            return back()->withErrors(['issue' => $exception->getMessage()]);
        }

        return back()->with('success', "Issued {$reservation->document_number}.");
    }
}
