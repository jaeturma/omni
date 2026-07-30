<?php

namespace App\ValueObjects;

use DomainException;
use Illuminate\Support\Carbon;

final readonly class FinancialReportParameters
{
    public Carbon $startDate;

    public Carbon $endDate;

    public Carbon $asOfDate;

    public ?Carbon $comparisonStartDate;

    public ?Carbon $comparisonEndDate;

    public function __construct(
        string $startDate,
        string $endDate,
        string $asOfDate,
        public ?int $fiscalPeriodId = null,
        ?string $comparisonStartDate = null,
        ?string $comparisonEndDate = null,
        public bool $showZeroBalances = false,
    ) {
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate = Carbon::parse($endDate)->startOfDay();
        $this->asOfDate = Carbon::parse($asOfDate)->startOfDay();
        $this->comparisonStartDate = $comparisonStartDate === null ? null : Carbon::parse($comparisonStartDate)->startOfDay();
        $this->comparisonEndDate = $comparisonEndDate === null ? null : Carbon::parse($comparisonEndDate)->startOfDay();

        if ($this->startDate->gt($this->endDate) || $this->endDate->gt($this->asOfDate)) {
            throw new DomainException('The report date range must end on or before the as-of date.');
        }

        if (($this->comparisonStartDate === null) !== ($this->comparisonEndDate === null)) {
            throw new DomainException('Both comparative-period dates are required.');
        }

        if ($this->comparisonStartDate !== null && (
            $this->comparisonStartDate->gt($this->comparisonEndDate)
            || $this->comparisonEndDate->gte($this->startDate)
        )) {
            throw new DomainException('The comparative period must precede the primary period.');
        }
    }

    /** @return array<string, string|int|bool|null> */
    public function outputParameters(): array
    {
        return [
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
            'as_of_date' => $this->asOfDate->toDateString(),
            'fiscal_period_id' => $this->fiscalPeriodId,
            'comparison_start_date' => $this->comparisonStartDate?->toDateString(),
            'comparison_end_date' => $this->comparisonEndDate?->toDateString(),
            'show_zero_balances' => $this->showZeroBalances,
        ];
    }
}
