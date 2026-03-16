<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'partner_firebase_id' => 'partner_' . $this->faker->uuid(),
            'agreement_id' => null,
            'email' => $this->faker->unique()->safeEmail(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone' => $this->faker->phoneNumber(),
            'country' => 'FR',
            'language' => 'fr',
            'firebase_uid' => null,
            'affiliate_code' => null,
            'invite_token' => Str::random(64),
            'status' => 'invited',
            'invited_at' => now(),
            'registered_at' => null,
            'last_activity_at' => null,
            'total_calls' => 0,
            'total_spent_cents' => 0,
            'total_discount_cents' => 0,
            'tags' => [],
            'custom_fields' => [],
        ];
    }

    public function forAgreement(Agreement $agreement): static
    {
        return $this->state([
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'agreement_id' => $agreement->id,
        ]);
    }

    public function registered(string $firebaseUid = null): static
    {
        return $this->state([
            'status' => 'registered',
            'firebase_uid' => $firebaseUid ?? 'uid_' . Str::random(20),
            'registered_at' => now(),
        ]);
    }

    public function active(string $firebaseUid = null): static
    {
        return $this->state([
            'status' => 'active',
            'firebase_uid' => $firebaseUid ?? 'uid_' . Str::random(20),
            'registered_at' => now()->subWeek(),
            'last_activity_at' => now(),
            'total_calls' => 3,
            'total_spent_cents' => 15000,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }

    public function withCalls(int $count, int $totalSpentCents = 0): static
    {
        return $this->state([
            'total_calls' => $count,
            'total_spent_cents' => $totalSpentCents,
        ]);
    }
}
