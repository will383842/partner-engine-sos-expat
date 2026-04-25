<?php

namespace Database\Factories;

use App\Models\LegalDocumentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class LegalDocumentTemplateFactory extends Factory
{
    protected $model = LegalDocumentTemplate::class;

    public function definition(): array
    {
        return [
            'kind' => LegalDocumentTemplate::KIND_CGV_B2B,
            'language' => 'fr',
            'version' => '1.0.0',
            'title' => 'Conditions Générales de Vente B2B',
            'body_html' => '<h2>Article 1</h2><p>Partenaire : {{partner_name}}.</p>',
            'variables' => ['partner_name', 'billing_rate'],
            'is_published' => true,
            'published_at' => now(),
            'published_by' => 'admin:test',
        ];
    }

    public function dpa(): static
    {
        return $this->state([
            'kind' => LegalDocumentTemplate::KIND_DPA,
            'title' => 'DPA RGPD',
            'body_html' => '<h2>DPA</h2><p>Sous-traitance pour {{partner_name}}.</p>',
        ]);
    }

    public function orderForm(): static
    {
        return $this->state([
            'kind' => LegalDocumentTemplate::KIND_ORDER_FORM,
            'title' => 'Bon de commande',
            'body_html' => '<h2>Bon</h2><p>Tarif : {{billing_rate}} {{billing_currency}}.</p>',
        ]);
    }

    public function unpublished(): static
    {
        return $this->state([
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function version(string $v): static
    {
        return $this->state(['version' => $v]);
    }
}
