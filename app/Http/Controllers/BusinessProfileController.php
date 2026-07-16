<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertBusinessProfileRequest;
use App\Models\BusinessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BusinessProfileController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('viewAny', BusinessProfile::class);

        return view('business-profile.edit', ['businessProfile' => BusinessProfile::active()->first()]);
    }

    public function update(UpsertBusinessProfileRequest $request): RedirectResponse
    {
        $profile = BusinessProfile::active()->first() ?? new BusinessProfile;
        $profile->fill($request->safe()->except('logo'));
        $profile->created_by ??= $request->user()->id;
        $profile->updated_by = $request->user()->id;

        if ($request->hasFile('logo')) {
            Storage::disk('public')->delete($profile->logo_path ?? '');
            $profile->logo_path = $request->file('logo')->store('business-logos', 'public');
        }

        $profile->save();

        return redirect()->route('business-profile.edit')->with('success', 'Business profile saved.');
    }
}
