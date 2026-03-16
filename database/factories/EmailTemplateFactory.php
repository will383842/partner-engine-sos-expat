<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'partner_firebase_id' => 'partner_' . $this->faker->uuid(),
            'type' => 'invitation',
            'subject' => 'Vous êtes invité par {partnerName}',
            'body_html' => '<p>Bonjour {firstName}, découvrez SOS-Expat avec {discountLabel}. <a href="{invitationLink}">Inscrivez-vous</a></p>',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function reminder(): static
    {
        return $this->state(['type' => 'reminder']);
    }

    public function expiration(): static
    {
        return $this->state(['type' => 'expiration']);
    }
}
