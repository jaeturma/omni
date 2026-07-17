<?php

namespace App\Actions;

use App\Enums\GovernmentDeductionStatus;
use App\Models\GovernmentDeduction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionGovernmentDeduction
{
    /** @param array<string, mixed> $data */
    public function handle(GovernmentDeduction $deduction, GovernmentDeductionStatus $target, int $userId, array $data = []): GovernmentDeduction
    {
        return DB::transaction(function () use ($deduction, $target, $userId, $data) {
            $locked = GovernmentDeduction::lockForUpdate()->findOrFail($deduction->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This deduction transition is not allowed.']);
            }
            if ($target === GovernmentDeductionStatus::Verified && (! $locked->certificate_number || ! $locked->certificate_date)) {
                throw ValidationException::withMessages(['status' => 'A certificate number and date are required before verification.']);
            }
            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === GovernmentDeductionStatus::Verified) {
                $changes += ['verified_at' => now(), 'verified_by' => $userId];
            }
            if ($target === GovernmentDeductionStatus::Voided) {
                $changes += ['voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $data['reason']];
            }
            $locked->update($changes);

            return $locked;
        }, 3);
    }
}
