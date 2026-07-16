<?php

namespace App\Actions;

use App\Models\BusinessProfile;
use App\Models\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateFiscalYear
{
    /** @param array{name: string, starts_on: string, ends_on: string, is_current: bool} $data */
    public function handle(BusinessProfile $business, array $data, int $userId): FiscalYear
    {
        return DB::transaction(function () use ($business, $data, $userId): FiscalYear {
            if ($data['is_current']) {
                FiscalYear::query()->whereBelongsTo($business)->where('is_current', true)->update(['is_current' => false, 'current_marker' => null]);
            }

            $year = FiscalYear::query()->create($data + ['business_profile_id' => $business->id, 'created_by' => $userId]);
            $start = CarbonImmutable::parse($data['starts_on']);
            $end = CarbonImmutable::parse($data['ends_on']);
            $month = $start->startOfMonth();

            while ($month->lte($end)) {
                $periodStart = $month->max($start);
                $periodEnd = $month->endOfMonth()->min($end);
                $year->periods()->create([
                    'name' => $month->format('F Y'), 'starts_on' => $periodStart, 'ends_on' => $periodEnd,
                    'calendar_year' => $month->year, 'calendar_month' => $month->month,
                    'calendar_quarter' => (int) ceil($month->month / 3),
                ]);
                $month = $month->addMonth();
            }

            return $year->load('periods');
        });
    }
}
