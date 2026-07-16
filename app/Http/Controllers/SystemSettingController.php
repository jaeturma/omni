<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(SystemSettings $settings): View
    {
        Gate::authorize('system-settings.view');

        return view('system-settings.edit', ['settings' => $settings->all()]);
    }

    public function update(UpdateSystemSettingsRequest $request, SystemSettings $settings): RedirectResponse
    {
        $settings->update($request->validated('settings'), (int) $request->user()->id);

        return back()->with('success', 'System settings saved.');
    }
}
