<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_firebase_id' => 'uid_' . $this->faker->uuid(),
            'actor_role' => 'admin',
            'action' => 'agreement.created',
            'resource_type' => 'agreement',
            'resource_id' => $this->faker->randomNumber(),
            'details' => ['test' => true],
            'ip_address' => $this->faker->ipv4(),
            'created_at' => now(),
        ];
    }
}
