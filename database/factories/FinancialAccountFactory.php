<?php

namespace Database\Factories;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialAccount> */
class FinancialAccountFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('ACC-####'), 'name' => fake()->words(3, true), 'type' => FinancialAccountType::BankChecking,
            'account_number' => fake()->numerify('############'), 'account_holder_name' => fake()->name(), 'currency' => 'PHP',
            'opening_balance' => '0.0000', 'active' => true, 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }
}
