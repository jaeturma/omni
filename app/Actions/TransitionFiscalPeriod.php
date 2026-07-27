<?php

namespace App\Actions;

use App\Models\FiscalPeriod;
use App\Models\FiscalPeriodEvent;
use App\Services\PeriodCloseChecklist;
use DomainException;
use Illuminate\Support\Facades\DB;

class TransitionFiscalPeriod
{
    public function __construct(private PeriodCloseChecklist $checklist) {}

    /** @param list<string> $overrides */
    public function handle(
        FiscalPeriod $period,
        string $target,
        int $userId,
        ?string $notes,
        array $overrides,
        int $expectedVersion,
    ): FiscalPeriod {
        return DB::transaction(function () use ($period, $target, $userId, $notes, $overrides, $expectedVersion): FiscalPeriod {
            $locked = FiscalPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('The fiscal period changed after this page was loaded. Refresh and try again.');
            }
            $from = $locked->status;
            $checklist = null;

            if ($target === 'closed') {
                if ($from !== 'open') {
                    throw new DomainException('Only an open fiscal period may be closed.');
                }
                $checklist = $this->checklist->generate($locked);
                if ($this->checklist->hasBlockingFailures($checklist, $overrides)) {
                    throw new DomainException('The fiscal period cannot close while checklist items are unresolved.');
                }
                if ($overrides !== [] && blank($notes)) {
                    throw new DomainException('Documented notes are required when overriding a checklist warning.');
                }
                $changes = [
                    'status' => 'closed', 'closed_at' => now(), 'closed_by' => $userId,
                    'close_notes' => $notes, 'close_checklist' => $checklist, 'close_overrides' => $overrides,
                ];
            } elseif ($target === 'locked') {
                if ($from !== 'closed') {
                    throw new DomainException('Only a closed fiscal period may be locked.');
                }
                $changes = ['status' => 'locked', 'locked_at' => now(), 'locked_by' => $userId, 'lock_notes' => $notes];
            } elseif ($target === 'open') {
                if (! in_array($from, ['closed', 'locked'], true)) {
                    throw new DomainException('Only a closed or locked fiscal period may be reopened.');
                }
                if (blank($notes)) {
                    throw new DomainException('A reason is required to reopen a fiscal period.');
                }
                $changes = [
                    'status' => 'open', 'reopened_at' => now(), 'reopened_by' => $userId,
                    'reopen_reason' => $notes, 'locked_at' => null, 'locked_by' => null, 'lock_notes' => null,
                ];
            } else {
                throw new DomainException('Unsupported fiscal period transition.');
            }

            $locked->update($changes + ['lock_version' => $locked->lock_version + 1]);
            FiscalPeriodEvent::query()->create([
                'fiscal_period_id' => $locked->id, 'action' => $target === 'open' ? 'reopened' : $target,
                'from_status' => $from, 'to_status' => $target, 'notes' => $notes,
                'checklist' => $checklist, 'overrides' => $overrides ?: null,
                'performed_by' => $userId, 'performed_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }
}
