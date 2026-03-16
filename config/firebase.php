<?php

return [
    'default' => env('FIREBASE_PROJECT', 'app'),

    'projects' => [
        'app' => [
            'credentials' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-credentials.json')),
        ],
    ],
];
