@props(['template'])
@php
    // Sample variables for preview (real values are substituted at draft generation per agreement).
    $sampleVars = [
        'partner_name' => '« Nom du partenaire »',
        'partner_legal_name' => '« Raison sociale du partenaire »',
        'partner_address' => '« Adresse du partenaire »',
        'partner_siret' => '« SIRET partenaire »',
        'partner_vat' => '« TVA intracommunautaire »',
        'partner_signatory_name' => '« Nom du signataire »',
        'partner_signatory_title' => '« Fonction »',
        'billing_rate' => '0,50',
        'monthly_base_fee' => '500,00',
        'billing_currency' => 'EUR',
        'payment_terms_days' => 30,
        'starts_at' => now()->format('d/m/Y'),
        'expires_at' => null,
        'call_types_allowed' => ['lawyer', 'expat'],
        'max_subscribers' => 0,
        'max_calls_per_subscriber' => 0,
        'default_subscriber_duration_days' => 365,
        'pricing_tiers' => null,
        'partner_legal_language' => $template->language,
        'provider_legal_name' => config('legal.provider_legal_name'),
        'provider_address' => config('legal.provider_address'),
        'provider_siret' => config('legal.provider_siret'),
        'provider_vat' => config('legal.provider_vat'),
        'provider_email' => config('legal.provider_email'),
        'provider_dpo_email' => config('legal.provider_dpo_email'),
        'provider_jurisdiction' => config('legal.provider_jurisdiction'),
    ];

    // Render: custom body_html if set (with simple {{var}} substitution),
    // otherwise the default Blade view at resources/views/legal/body/{kind}.blade.php.
    if (filled($template->body_html)) {
        $rendered = $template->body_html;
        foreach ($sampleVars as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $rendered = str_replace(['{{' . $k . '}}', '{{ ' . $k . ' }}'], (string) ($v ?? ''), $rendered);
            }
        }
        $isDefault = false;
    } else {
        $rendered = view("legal.body.{$template->kind}", ['vars' => $sampleVars, 'customClauses' => []])->render();
        $isDefault = true;
    }
@endphp
<div style="max-height: 70vh; overflow-y: auto; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; background: white; color: #111827;">
    <h2 style="margin-bottom: 1rem; font-size: 1.25rem; font-weight: 700;">{{ $template->title }}</h2>
    <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 1rem;">
        {{ $template->kind }} &middot; {{ $template->language }} &middot; v{{ $template->version }}
        @if($template->is_published)
            &middot; <span style="color: #047857;">Publié le {{ optional($template->published_at)->format('d/m/Y H:i') }}</span>
        @else
            &middot; <span style="color: #b91c1c;">Brouillon</span>
        @endif
        @if($isDefault)
            &middot; <span style="color: #6366f1; font-weight: 600;">Contenu par défaut (resources/views/legal/body/{{ $template->kind }}.blade.php)</span>
        @else
            &middot; <span style="color: #ea580c; font-weight: 600;">Contenu personnalisé</span>
        @endif
    </div>
    <div style="background: #fef3c7; border-left: 3px solid #f59e0b; padding: 0.5rem 0.75rem; margin-bottom: 1rem; font-size: 0.85rem; color: #78350f; border-radius: 0.25rem;">
        ℹ️ Aperçu avec valeurs de démonstration. À la génération réelle pour un partenaire, les variables sont remplacées par les valeurs de l'accord (nom, taux, devise, etc.).
    </div>
    <div class="prose max-w-none">
        {!! $rendered !!}
    </div>
</div>
