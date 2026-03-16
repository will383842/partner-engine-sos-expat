<?php

namespace Database\Factories;

use App\Models\Agreement;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgreementFactory extends Factory
{
    protected $model = Agreement::class;

    public function definition(): array
    {
        return [
            'partner_firebase_id' => 'partner_' . $this->faker->uuid(),
            'partner_name' => $this->faker->company(),
            'name' => 'Accord ' . $this->faker->word(),
            'status' => 'active',
            'discount_type' => 'fixed',
            'discount_value' => 300,
            'discount_max_cents' => null,
            'discount_label' => '-$3 sur chaque appel',
            'commission_per_call_lawyer' => 500,
            'commission_per_call_expat' => 300,
            'commission_type' => 'fixed',
            'commission_percent' => null,
            'max_subscribers' => null,
            'max_calls_per_subscriber' => null,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addYear(),
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }

    public function percentCommission(float $percent = 10.0): static
    {
        return $this->state([
            'commission_type' => 'percent',
            'commission_percent' => $percent,
        ]);
    }

    public function withMaxSubscribers(int $max): static
    {
        return $this->state(['max_subscribers' => $max]);
    }

    public function withMaxCallsPerSubscriber(int $max): static
    {
        return $this->state(['max_calls_per_subscriber' => $max]);
    }

    public function expiringIn(int $days): static
    {
        return $this->state([
            'status' => 'active',
            'expires_at' => now()->addDays($days),
        ]);
    }

    public function alreadyExpired(): static
    {
        return $this->state([
            'status' => 'active',
            'expires_at' => now()->subHour(),
        ]);
    }
}
