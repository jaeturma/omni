<?php

namespace App\Services;

use App\Models\TaxComplianceRule;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxRuleRegistry
{
    public const PERMISSIONS = [
        'tax-rules.view',
        'tax-rules.create',
        'tax-rules.update',
        'tax-rules.activate',
        'tax-rules.deactivate',
        'tax-rules.review',
    ];

    /** @param array<string, mixed> $data */
    public function create(TaxProfile $profile, array $data, User $reviewer): TaxComplianceRule
    {
        return DB::transaction(function () use ($profile, $data, $reviewer): TaxComplianceRule {
            $this->lockRules($profile);
            $this->assertNoOverlap($profile, $data);

            return $profile->complianceRules()->create($data + ['reviewed_by' => $reviewer->id]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(TaxComplianceRule $rule, array $data, User $reviewer): TaxComplianceRule
    {
        return DB::transaction(function () use ($rule, $data, $reviewer): TaxComplianceRule {
            $this->lockRules($rule->taxProfile);

            if ($rule->used_at !== null) {
                $rule->update(['active' => false]);
                $this->assertNoOverlap($rule->taxProfile, $data);

                return $rule->taxProfile->complianceRules()->create($data + [
                    'supersedes_id' => $rule->id,
                    'reviewed_by' => $reviewer->id,
                ]);
            }

            $this->assertNoOverlap($rule->taxProfile, $data, $rule);
            $rule->update($data + ['reviewed_by' => $reviewer->id]);

            return $rule->refresh();
        });
    }

    public function setActive(TaxComplianceRule $rule, bool $active): void
    {
        DB::transaction(function () use ($rule, $active): void {
            $this->lockRules($rule->taxProfile);
            if ($active) {
                $data = $rule->toArray();
                $data['active'] = true;
                $this->assertNoOverlap($rule->taxProfile, $data, $rule);
            }
            $rule->update(['active' => $active]);
        });
    }

    /** @param array<string, mixed> $data */
    public function review(TaxComplianceRule $rule, array $data, User $reviewer): void
    {
        $rule->update($data + ['reviewed_by' => $reviewer->id]);
    }

    public function resolve(TaxProfile $profile, string $formNumber, string $date): ?TaxComplianceRule
    {
        $registered = $profile->forms()->where('form_code', $formNumber)->where('active', true)->exists();

        return TaxComplianceRule::query()->whereBelongsTo($profile)->activeOn($date)
            ->where('bir_form_number', $formNumber)
            ->whereIn('taxpayer_applicability', ['any', $profile->taxpayer_type])
            ->whereIn('registration_applicability', ['any', $registered ? 'registered' : 'not_registered'])
            ->latest('effective_from')
            ->first();
    }

    /** @param array<string, mixed> $data */
    private function assertNoOverlap(TaxProfile $profile, array $data, ?TaxComplianceRule $except = null): void
    {
        if (! ($data['active'] ?? true)) {
            return;
        }

        $query = $profile->complianceRules()
            ->where('active', true)
            ->where('bir_form_number', $data['bir_form_number'])
            ->whereDate('effective_from', '<=', $data['effective_to'] ?: '9999-12-31')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $data['effective_from']));
        if ($except !== null) {
            $query->whereKeyNot($except->id);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'effective_from' => 'The effective period overlaps an active rule for this form and tax profile.',
            ]);
        }
    }

    private function lockRules(TaxProfile $profile): void
    {
        $profile->complianceRules()->lockForUpdate()->get(['id']);
    }
}
