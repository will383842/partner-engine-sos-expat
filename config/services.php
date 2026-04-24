<?php

return [
    'engine_api_key' => env('ENGINE_API_KEY'),

    'frontend_url' => env('FRONTEND_URL', 'https://www.sos-expat.com'),

    'telegram_engine' => [
        'url' => env('TELEGRAM_ENGINE_URL', 'https://engine-telegram-sos-expat.life-expat.com'),
        'api_key' => env('TELEGRAM_ENGINE_API_KEY'),
    ],

    'sos_call' => [
        'public_url' => env('SOS_CALL_URL', 'https://sos-call.sos-expat.com'),
        'admin_url' => env('ADMIN_URL', 'https://admin.sos-expat.com'),
        'enabled' => env('SOS_CALL_ENABLED', true),
        'default_billing_rate' => env('SOS_CALL_DEFAULT_BILLING_RATE', 3.00),
        'default_billing_currency' => env('SOS_CALL_DEFAULT_CURRENCY', 'EUR'),
        'default_payment_terms_days' => env('SOS_CALL_DEFAULT_PAYMENT_TERMS_DAYS', 15),
        'internal_cost_expat_cents' => env('SOS_CALL_INTERNAL_COST_EXPAT_CENTS', 1000),
        'internal_cost_lawyer_cents' => env('SOS_CALL_INTERNAL_COST_LAWYER_CENTS', 3000),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'firebase_partner_bridge' => [
        'url' => env('FIREBASE_PARTNER_BRIDGE_URL', 'https://us-central1-sos-urgently-ac307.cloudfunctions.net'),
        'api_key' => env('FIREBASE_PARTNER_BRIDGE_API_KEY', env('ENGINE_API_KEY')),
    ],

    'engine' => [
        'api_key' => env('ENGINE_API_KEY'),
    ],

    'firebase' => [
        'web_api_key' => env('FIREBASE_WEB_API_KEY'),
        'web_auth_domain' => env('FIREBASE_WEB_AUTH_DOMAIN'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'sentry' => [
        'dsn' => env('SENTRY_LARAVEL_DSN'),
    ],
];
