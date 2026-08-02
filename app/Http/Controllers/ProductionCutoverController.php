<?php

namespace App\Http\Controllers;

use App\Actions\TransitionProductionCutover;
use App\Http\Requests\StoreProductionCutoverRequest;
use App\Models\BackupRun;
use App\Models\ProductionCutover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductionCutoverController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ProductionCutover::class);

        return view('production-cutovers.index', [
            'cutovers' => ProductionCutover::query()->with(['backupRun:id,status,verified_at,restore_tested_at,offsite_copied', 'reviewer:id,name', 'activator:id,name'])->latest('cutover_date')->paginate(20),
            'backups' => BackupRun::query()->where('status', 'verified')->latest('verified_at')->get(['id', 'backup_class', 'verified_at', 'restore_tested_at', 'offsite_copied']),
        ]);
    }

    public function store(StoreProductionCutoverRequest $request): RedirectResponse
    {
        $cutover = ProductionCutover::query()->create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('production-cutovers.index')->with('success', "Cutover for {$cutover->cutover_date->toDateString()} created as draft.");
    }

    public function approve(ProductionCutover $productionCutover, TransitionProductionCutover $transition): RedirectResponse
    {
        Gate::authorize('approve', $productionCutover);
        $transition->approve($productionCutover, (int) auth()->id());

        return back()->with('success', 'Cutover report approved and locked.');
    }

    public function activate(ProductionCutover $productionCutover, TransitionProductionCutover $transition): RedirectResponse
    {
        Gate::authorize('activate', $productionCutover);
        $transition->activate($productionCutover, (int) auth()->id());

        return back()->with('success', 'Production cutover activated.');
    }
}
