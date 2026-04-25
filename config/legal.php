<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SOS-Expat / World Expat — Provider legal identity
    |--------------------------------------------------------------------------
    | These values are injected into every generated legal document
    | (CGV B2B, DPA, Order Form). Override per-environment via env vars.
    */
    /*
    | World Expat OÜ — société estonienne (Osaühing).
    | provider_siret = registrikood (code registre commercial estonien, 8 chiffres)
    | provider_vat   = numéro TVA UE estonien, format EE + 9 chiffres
    | provider_jurisdiction = tribunal compétent en cas de litige (Harju Maakohus à Tallinn)
    */
    'provider_legal_name' => env('LEGAL_PROVIDER_LEGAL_NAME', 'World Expat OÜ'),
    'provider_address' => env('LEGAL_PROVIDER_ADDRESS', ''),
    'provider_siret' => env('LEGAL_PROVIDER_SIRET', ''),
    'provider_vat' => env('LEGAL_PROVIDER_VAT', ''),
    'provider_email' => env('LEGAL_PROVIDER_EMAIL', 'contact@sos-expat.com'),
    'provider_dpo_email' => env('LEGAL_PROVIDER_DPO_EMAIL', 'dpo@sos-expat.com'),
    'provider_jurisdiction' => env('LEGAL_PROVIDER_JURISDICTION', 'Harju Maakohus, Tallinn'),
];
