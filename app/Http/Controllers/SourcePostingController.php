<?php

namespace App\Http\Controllers;

use App\Models\SourcePosting;
use App\Services\AutomaticSourcePosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SourcePostingController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SourcePosting::class);
        $postings = SourcePosting::query()->with('journalEntry:id,journal_number')
            ->latest('last_attempt_at')->paginate(25);

        return view('source-postings.index', ['postings' => $postings]);
    }

    public function retry(SourcePosting $sourcePosting, AutomaticSourcePosting $posting): RedirectResponse
    {
        Gate::authorize('retry', $sourcePosting);
        $result = $posting->retry($sourcePosting, request()->user()->id);

        return back()->with(
            $result->status === 'posted' ? 'success' : 'error',
            $result->status === 'posted' ? 'Source posting completed.' : 'Source posting failed again.',
        );
    }

    public function rebuildLink(SourcePosting $sourcePosting, AutomaticSourcePosting $posting): RedirectResponse
    {
        Gate::authorize('rebuildLink', $sourcePosting);
        $posting->rebuildLink($sourcePosting, request()->user()->id);

        return back()->with('success', 'Source-to-journal link rebuilt.');
    }
}
