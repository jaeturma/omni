<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewTaxComplianceRuleRequest;
use App\Http\Requests\StoreTaxComplianceRuleRequest;
use App\Http\Requests\UpdateTaxComplianceRuleRequest;
use App\Models\TaxComplianceRule;
use App\Models\TaxProfile;
use App\Services\TaxRuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaxComplianceRuleController extends Controller
{
    public function __construct(private TaxRuleRegistry $registry) {}

    public function index(): View|RedirectResponse
    {
        Gate::authorize('viewAny', TaxComplianceRule::class);
        $profile = TaxProfile::query()->where('active', true)->first();
        if ($profile === null) {
            return to_route('tax-profile.edit')->with('warning', 'Create the active tax profile before configuring compliance rules.');
        }

        return view('tax-rules.index', [
            'profile' => $profile,
            'rules' => $profile->complianceRules()->with('reviewer:id,name')->latest('effective_from')->paginate(25),
        ]);
    }

    public function store(StoreTaxComplianceRuleRequest $request): RedirectResponse
    {
        $profile = TaxProfile::query()->where('active', true)->firstOrFail();
        $this->registry->create($profile, $request->validated(), $request->user());

        return to_route('tax-rules.index')->with('success', 'Tax compliance rule created.');
    }

    public function edit(TaxComplianceRule $taxComplianceRule): View
    {
        Gate::authorize('update', $taxComplianceRule);

        return view('tax-rules.edit', ['rule' => $taxComplianceRule]);
    }

    public function update(UpdateTaxComplianceRuleRequest $request, TaxComplianceRule $taxComplianceRule): RedirectResponse
    {
        $this->registry->update($taxComplianceRule, $request->validated(), $request->user());

        return to_route('tax-rules.index')->with('success', 'Tax compliance rule updated.');
    }

    public function activate(TaxComplianceRule $taxComplianceRule): RedirectResponse
    {
        Gate::authorize('activate', $taxComplianceRule);
        $this->registry->setActive($taxComplianceRule, true);

        return back()->with('success', 'Tax compliance rule activated.');
    }

    public function deactivate(TaxComplianceRule $taxComplianceRule): RedirectResponse
    {
        Gate::authorize('deactivate', $taxComplianceRule);
        $this->registry->setActive($taxComplianceRule, false);

        return back()->with('success', 'Tax compliance rule deactivated.');
    }

    public function review(ReviewTaxComplianceRuleRequest $request, TaxComplianceRule $taxComplianceRule): RedirectResponse
    {
        $this->registry->review($taxComplianceRule, $request->validated(), $request->user());

        return back()->with('success', 'Official reference review recorded.');
    }
}
