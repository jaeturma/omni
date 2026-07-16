<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxRateRequest;
use App\Http\Requests\UpdateTaxProfileRequest;
use App\Models\BusinessProfile;
use App\Models\TaxProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaxProfileController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('viewAny', TaxProfile::class);
        $business = BusinessProfile::active()->firstOrFail();

        return view('tax-profile.edit', ['taxProfile' => $business->taxProfile()->with(['rates', 'forms'])->first()]);
    }

    public function update(UpdateTaxProfileRequest $request): RedirectResponse
    {
        $business = BusinessProfile::active()->firstOrFail();
        DB::transaction(function () use ($request, $business): void {
            $data = $request->safe()->except('forms');
            $data['active'] = true;
            $profile = TaxProfile::query()->updateOrCreate(['business_profile_id' => $business->id, 'active_marker' => 1], $data);
            $profile->forms()->delete();
            foreach ($request->validated('forms', []) as $form) {
                $profile->forms()->create(['form_code' => $form, 'filing_frequency' => $profile->filing_frequency, 'active' => true]);
            }
        });

        return back()->with('success', 'Tax profile saved.');
    }

    public function storeRate(StoreTaxRateRequest $request): RedirectResponse
    {
        TaxProfile::query()->where('active', true)->firstOrFail()->rates()->create($request->validated() + ['active' => true]);

        return back()->with('success', 'Tax rate added.');
    }
}
