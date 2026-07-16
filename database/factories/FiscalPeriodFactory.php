<?php

namespace Database\Factories;

use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalPeriod>
 */
class FiscalPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fiscal_year_id' => FiscalYear::factory(), 'name' => 'January 2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31',
            'calendar_year' => 2026, 'calendar_month' => 1, 'calendar_quarter' => 1, 'status' => 'open',
        ];
    }
}
