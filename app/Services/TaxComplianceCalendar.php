<?php

namespace App\Services;

use App\Models\TaxComplianceRule;
use App\Models\TaxObligation;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxComplianceCalendar
{
    public const PERMISSIONS = ['tax-calendar.view', 'tax-calendar.generate', 'tax-calendar.update', 'tax-calendar.assign-reviewer'];

    public const STATUSES = ['upcoming', 'open', 'preparing', 'for_review', 'ready_to_file', 'filed', 'paid', 'amended', 'overdue', 'not_applicable'];

    private const TRANSITIONS = [
        'upcoming' => ['open', 'not_applicable'],
        'open' => ['preparing', 'overdue', 'not_applicable'],
        'preparing' => ['open', 'for_review', 'overdue', 'not_applicable'],
        'for_review' => ['preparing', 'ready_to_file', 'overdue', 'not_applicable'],
        'ready_to_file' => ['for_review', 'filed', 'overdue', 'not_applicable'],
        'filed' => ['paid', 'amended'],
        'paid' => ['amended'],
        'amended' => ['preparing', 'for_review', 'ready_to_file', 'filed', 'paid'],
        'overdue' => ['preparing', 'for_review', 'ready_to_file', 'filed', 'not_applicable'],
        'not_applicable' => ['open'],
    ];

    public function generate(TaxProfile $profile, int $fromYear, int $throughYear): int
    {
        return DB::transaction(function () use ($profile, $fromYear, $throughYear): int {
            $registeredForms = $profile->forms()->where('active', true)->pluck('form_code');
            $rules = $profile->complianceRules()->where('active', true)
                ->whereIn('bir_form_number', $registeredForms)
                ->whereIn('taxpayer_applicability', ['any', $profile->taxpayer_type])
                ->whereIn('registration_applicability', ['any', 'registered'])
                ->get();
            $created = 0;

            foreach ($rules as $rule) {
                foreach ($this->periods($rule, $fromYear, $throughYear) as $definition) {
                    if (! $this->ruleApplies($rule, $profile, $definition['start'], $definition['end'])) {
                        continue;
                    }
                    $periodStart = CarbonImmutable::parse($definition['start']);
                    $periodEnd = CarbonImmutable::parse($definition['end']);
                    $period = TaxPeriod::query()->firstOrCreate([
                        'tax_profile_id' => $profile->id,
                        'frequency' => $rule->filing_frequency,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                    ], [
                        'capture_start' => $this->captureStart($profile, $rule, $definition['start']),
                        'tax_year' => $definition['year'],
                        'quarter' => $definition['quarter'],
                        'label' => $definition['label'],
                    ]);
                    $obligation = $period->obligations()->firstOrCreate([
                        'bir_form_number' => $rule->bir_form_number,
                    ], $this->obligationData($rule, $definition['end']));
                    if ($obligation->wasRecentlyCreated) {
                        $created++;
                        $rule->update(['used_at' => $rule->used_at ?? now()]);
                    }
                }
            }

            return $created;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(TaxObligation $obligation, array $data): void
    {
        if (isset($data['status']) && $data['status'] !== $obligation->status) {
            $allowed = self::TRANSITIONS[$obligation->status] ?? [];
            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'The requested tax-obligation status transition is not allowed.']);
            }
            $data += $this->statusFields($data['status']);
        }
        $obligation->update($data);
    }

    /** @param array<string, mixed> $data */
    public function adjustDeadline(TaxObligation $obligation, array $data, User $user): void
    {
        DB::transaction(function () use ($obligation, $data, $user): void {
            $previous = $obligation->effectiveDueDate()->toDateString();
            $obligation->deadlineAdjustments()->create($data + [
                'previous_due_date' => $previous,
                'adjusted_by' => $user->id,
            ]);
            $obligation->update(['adjusted_due_date' => $data['adjusted_due_date']]);
        });
    }

    /** @return array<int, array{start: string, end: string, year: int, quarter: int|null, label: string}> */
    private function periods(TaxComplianceRule $rule, int $fromYear, int $throughYear): array
    {
        $periods = [];
        foreach (range($fromYear, $throughYear) as $year) {
            if ($rule->filing_frequency === 'annual') {
                $periods[] = ['start' => "$year-01-01", 'end' => "$year-12-31", 'year' => $year, 'quarter' => null, 'label' => (string) $year];

                continue;
            }
            if ($rule->filing_frequency !== 'quarterly') {
                continue;
            }
            $quarters = $rule->applicable_quarters ?: [1, 2, 3, 4];
            foreach ($quarters as $quarter) {
                $start = CarbonImmutable::create($year, (($quarter - 1) * 3) + 1, 1);
                $periods[] = ['start' => $start->toDateString(), 'end' => $start->endOfQuarter()->toDateString(), 'year' => $year, 'quarter' => $quarter, 'label' => "Q$quarter $year"];
            }
        }

        return $periods;
    }

    private function ruleApplies(TaxComplianceRule $rule, TaxProfile $profile, string $start, string $end): bool
    {
        return $profile->registration_start_date->toDateString() <= $end
            && $rule->effective_from->toDateString() <= $end
            && ($rule->effective_to === null || $rule->effective_to->toDateString() >= $start);
    }

    private function captureStart(TaxProfile $profile, TaxComplianceRule $rule, string $periodStart): string
    {
        return max($periodStart, $profile->registration_start_date->toDateString(), $rule->effective_from->toDateString());
    }

    /** @return array<string, mixed> */
    private function obligationData(TaxComplianceRule $rule, string $periodEnd): array
    {
        $dueDate = CarbonImmutable::parse($periodEnd)->startOfMonth()
            ->addMonths((int) $rule->deadline_months_after_period_end);
        $dueDate = $dueDate->day(min((int) $rule->deadline_day, $dueDate->daysInMonth));

        return [
            'tax_compliance_rule_id' => $rule->id,
            'tax_type' => $rule->tax_type,
            'original_due_date' => $dueDate->toDateString(),
            'deadline_rule_source' => $rule->deadline_rule,
            'status' => CarbonImmutable::parse($periodEnd)->isFuture() ? 'upcoming' : 'open',
            'rule_snapshot' => $rule->only([
                'id', 'tax_type', 'bir_form_number', 'form_title', 'filing_frequency', 'effective_from', 'effective_to',
                'tax_rate', 'tax_base_rule', 'credit_rule', 'deadline_rule', 'deadline_months_after_period_end',
                'deadline_day', 'applicable_quarters', 'amendment_supported', 'attachment_requirements',
                'official_reference_title', 'official_reference_url', 'last_reviewed_on',
            ]),
        ];
    }

    /** @return array<string, string> */
    private function statusFields(string $status): array
    {
        return match ($status) {
            'filed' => ['filing_status' => 'filed'],
            'paid' => ['filing_status' => 'filed', 'payment_status' => 'paid'],
            'amended' => ['filing_status' => 'filed', 'amendment_status' => 'amended'],
            'not_applicable' => ['filing_status' => 'not_applicable', 'payment_status' => 'not_applicable', 'amendment_status' => 'not_applicable'],
            default => [],
        };
    }
}
