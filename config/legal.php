<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SOS-Expat / World Expat — Provider legal identity
    |--------------------------------------------------------------------------
    | These values are injected into every generated legal document
    | (CGV B2B, DPA, Order Form). Override per-environment via env vars.
    */
    'provider_legal_name' => env('LEGAL_PROVIDER_LEGAL_NAME', 'World Expat Network SAS'),
    'provider_address' => env('LEGAL_PROVIDER_ADDRESS', ''),
    'provider_siret' => env('LEGAL_PROVIDER_SIRET', ''),
    'provider_vat' => env('LEGAL_PROVIDER_VAT', ''),
    'provider_email' => env('LEGAL_PROVIDER_EMAIL', 'contact@sos-expat.com'),
    'provider_dpo_email' => env('LEGAL_PROVIDER_DPO_EMAIL', 'dpo@sos-expat.com'),
    'provider_jurisdiction' => env('LEGAL_PROVIDER_JURISDICTION', 'Tribunal de Commerce de Paris'),
];
