<?php

namespace Database\Factories;

use App\Models\PartnerMonthlyStat;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerMonthlyStatFactory extends Factory
{
    protected $model = PartnerMonthlyStat::class;

    public function definition(): array
    {
        return [
            'partner_firebase_id' => 'partner_' . $this->faker->uuid(),
            'month' => now()->format('Y-m'),
            'total_subscribers' => $this->faker->numberBetween(0, 100),
            'new_subscribers' => $this->faker->numberBetween(0, 20),
            'active_subscribers' => $this->faker->numberBetween(0, 50),
            'total_calls' => $this->faker->numberBetween(0, 200),
            'total_revenue_cents' => $this->faker->numberBetween(0, 500000),
            'total_commissions_cents' => $this->faker->numberBetween(0, 100000),
            'total_discounts_cents' => $this->faker->numberBetween(0, 50000),
            'conversion_rate' => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
