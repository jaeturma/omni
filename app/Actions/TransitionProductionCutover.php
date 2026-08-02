<?php

namespace App\Actions;

use App\Models\ProductionCutover;
use App\Services\AuditLogger;
use App\Services\ProductionCutoverReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionProductionCutover
{
    public function __construct(private ProductionCutoverReport $report, private AuditLogger $audit) {}

    public function approve(ProductionCutover $cutover, int $userId): ProductionCutover
    {
        return DB::transaction(function () use ($cutover, $userId): ProductionCutover {
            $cutover = ProductionCutover::query()->with('backupRun')->lockForUpdate()->findOrFail($cutover->id);
            if ($cutover->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only a draft cutover may be approved.']);
            }
            $snapshot = $this->report->generate($cutover);
            if ($failures = $this->report->failures($snapshot)) {
                throw ValidationException::withMessages(['status' => $failures]);
            }
            $cutover->update(['status' => 'approved', 'report_snapshot' => $snapshot, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
            $this->audit->log('production_cutover.approved', $cutover, [], ['cutover_date' => $snapshot['cutover_date']]);

            return $cutover;
        }, 3);
    }

    public function activate(ProductionCutover $cutover, int $userId): ProductionCutover
    {
        return DB::transaction(function () use ($cutover, $userId): ProductionCutover {
            $cutover = ProductionCutover::query()->lockForUpdate()->findOrFail($cutover->id);
            if ($cutover->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Only an approved cutover may be activated.']);
            }
            $cutover->update(['status' => 'activated', 'activated_by' => $userId, 'activated_at' => now()]);
            $this->audit->log('production_cutover.activated', $cutover, [], ['cutover_date' => $cutover->cutover_date->toDateString()]);

            return $cutover;
        }, 3);
    }
}
