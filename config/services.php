<?php

return [
    'engine_api_key' => env('ENGINE_API_KEY'),

    'frontend_url' => env('FRONTEND_URL', 'https://www.sos-expat.com'),

    'telegram_engine' => [
        'url' => env('TELEGRAM_ENGINE_URL', 'https://engine-telegram-sos-expat.life-expat.com'),
        'api_key' => env('TELEGRAM_ENGINE_API_KEY'),
    ],
];
