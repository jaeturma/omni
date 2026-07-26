<?php

namespace App\Http\Controllers;

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\AccountingSourceType;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Http\Requests\JournalEntryRequest;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', JournalEntry::class);

        return view('journal-entries.index', ['entries' => JournalEntry::query()->with('fiscalPeriod:id,name')->latest('journal_date')->latest('id')->paginate(25)]);
    }

    public function create(): View
    {
        Gate::authorize('create', JournalEntry::class);

        return view('journal-entries.form', $this->formData());
    }

    public function store(JournalEntryRequest $request, SaveJournalEntry $save): RedirectResponse
    {
        $entry = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('journal-entries.show', $entry)->with('success', 'Journal entry draft saved.');
    }

    public function show(JournalEntry $journalEntry): View
    {
        Gate::authorize('view', $journalEntry);

        return view('journal-entries.show', ['entry' => $journalEntry->load(['lines.account', 'fiscalPeriod', 'postedBy'])]);
    }

    public function edit(JournalEntry $journalEntry): View
    {
        Gate::authorize('update', $journalEntry);

        return view('journal-entries.form', $this->formData() + ['entry' => $journalEntry->load('lines')]);
    }

    public function update(JournalEntryRequest $request, JournalEntry $journalEntry, SaveJournalEntry $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $journalEntry);

        return redirect()->route('journal-entries.show', $journalEntry)->with('success', 'Journal entry updated.');
    }

    public function transition(Request $request, JournalEntry $journalEntry, TransitionJournalEntry $transition): RedirectResponse
    {
        $request->validate(['status' => ['required', 'in:posted,voided'], 'void_reason' => ['nullable', 'required_if:status,voided', 'string', 'max:2000']]);
        $target = JournalEntryStatus::from((string) $request->input('status'));
        Gate::authorize($target === JournalEntryStatus::Posted ? 'post' : 'void', $journalEntry);
        $transition->handle($journalEntry, $target, $request->user()->id, $request->string('void_reason')->toString());

        return back()->with('success', 'Journal entry status updated.');
    }

    private function formData(): array
    {
        return ['periods' => FiscalPeriod::query()->where('status', 'open')->latest('starts_on')->get(),
            'accounts' => Account::query()->where('is_active', true)->where('is_postable', true)->ordered()->get(),
            'journalTypes' => JournalEntryType::cases(), 'sourceTypes' => AccountingSourceType::cases()];
    }
}
