<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustTaxObligationDeadlineRequest;
use App\Http\Requests\GenerateTaxCalendarRequest;
use App\Http\Requests\UpdateTaxObligationRequest;
use App\Models\TaxObligation;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\TaxComplianceCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaxCalendarController extends Controller
{
    public function __construct(private TaxComplianceCalendar $calendar) {}

    public function index(): View|RedirectResponse
    {
        Gate::authorize('viewAny', TaxObligation::class);
        $profile = TaxProfile::query()->where('active', true)->first();
        if ($profile === null) {
            return to_route('tax-profile.edit')->with('warning', 'Create the active tax profile before generating a compliance calendar.');
        }

        return view('tax-calendar.index', [
            'obligations' => TaxObligation::query()->whereIn('tax_period_id', $profile->taxPeriods()->select('id'))
                ->with(['taxPeriod:id,label,period_start,period_end,capture_start', 'assignedReviewer:id,name', 'deadlineAdjustments.adjustedBy:id,name'])
                ->orderBy('original_due_date')->paginate(30),
            'reviewers' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function generate(GenerateTaxCalendarRequest $request): RedirectResponse
    {
        $profile = TaxProfile::query()->where('active', true)->firstOrFail();
        $created = $this->calendar->generate($profile, $request->integer('from_year'), $request->integer('through_year'));

        return back()->with('success', "$created tax obligations generated.");
    }

    public function update(UpdateTaxObligationRequest $request, TaxObligation $taxObligation): RedirectResponse
    {
        $this->calendar->update($taxObligation, $request->validated());

        return back()->with('success', 'Tax obligation updated.');
    }

    public function adjustDeadline(AdjustTaxObligationDeadlineRequest $request, TaxObligation $taxObligation): RedirectResponse
    {
        $this->calendar->adjustDeadline($taxObligation, $request->validated(), $request->user());

        return back()->with('success', 'Deadline adjustment recorded.');
    }
}
