<?php

namespace Database\Factories;

use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriberActivityFactory extends Factory
{
    protected $model = SubscriberActivity::class;

    public function definition(): array
    {
        return [
            'subscriber_id' => Subscriber::factory(),
            'partner_firebase_id' => 'partner_' . $this->faker->uuid(),
            'type' => 'call_completed',
            'call_session_id' => $this->faker->uuid(),
            'provider_type' => $this->faker->randomElement(['lawyer', 'expat']),
            'call_duration_seconds' => $this->faker->numberBetween(60, 3600),
            'amount_paid_cents' => $this->faker->numberBetween(1000, 10000),
            'discount_applied_cents' => 300,
            'commission_earned_cents' => 500,
            'metadata' => [],
            'created_at' => now(),
        ];
    }

    public function callCompleted(): static
    {
        return $this->state(['type' => 'call_completed']);
    }

    public function registered(): static
    {
        return $this->state([
            'type' => 'registered',
            'call_session_id' => null,
            'provider_type' => null,
            'call_duration_seconds' => null,
            'amount_paid_cents' => null,
            'discount_applied_cents' => null,
            'commission_earned_cents' => null,
        ]);
    }

    public function invitationSent(): static
    {
        return $this->state([
            'type' => 'invitation_sent',
            'call_session_id' => null,
            'provider_type' => null,
            'call_duration_seconds' => null,
            'amount_paid_cents' => null,
            'discount_applied_cents' => null,
            'commission_earned_cents' => null,
        ]);
    }
}
