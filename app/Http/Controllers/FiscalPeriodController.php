<?php

namespace App\Http\Controllers;

use App\Actions\TransitionFiscalPeriod;
use App\Http\Requests\UpdateFiscalPeriodStatusRequest;
use App\Models\FiscalPeriod;
use App\Services\PeriodCloseChecklist;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FiscalPeriodController extends Controller
{
    public function show(FiscalPeriod $fiscalPeriod, PeriodCloseChecklist $checklist): View
    {
        Gate::authorize('view', $fiscalPeriod);
        $fiscalPeriod->load(['fiscalYear:id,name', 'closedBy:id,name', 'lockedBy:id,name', 'reopenedBy:id,name',
            'events.performedBy:id,name']);

        return view('fiscal-periods.show', [
            'period' => $fiscalPeriod,
            'checklist' => $fiscalPeriod->status === 'open' ? $checklist->generate($fiscalPeriod) : $fiscalPeriod->close_checklist,
        ]);
    }

    public function preclose(FiscalPeriod $fiscalPeriod, PeriodCloseChecklist $checklist): View
    {
        Gate::authorize('preclose', $fiscalPeriod);
        $fiscalPeriod->load(['fiscalYear:id,name', 'events.performedBy:id,name']);

        return view('fiscal-periods.show', ['period' => $fiscalPeriod, 'checklist' => $checklist->generate($fiscalPeriod)]);
    }

    public function update(UpdateFiscalPeriodStatusRequest $request, FiscalPeriod $fiscalPeriod, TransitionFiscalPeriod $action): RedirectResponse
    {
        $status = $request->string('status')->toString();
        $overrides = $request->boolean('override_open_adjustments') ? ['open_adjustments'] : [];

        try {
            $action->handle(
                $fiscalPeriod,
                $status,
                (int) $request->user()->id,
                $request->validated('notes'),
                $overrides,
                $request->integer('lock_version'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', "Fiscal period {$status}.");
    }
}
