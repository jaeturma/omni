<?php

namespace Database\Factories;

use App\Models\BusinessProfile;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_profile_id' => BusinessProfile::factory(), 'name' => 'FY 2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_current' => false,
            'created_by' => User::factory(),
        ];
    }
}
