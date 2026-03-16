<?php

return [
    'projects' => [
        'app' => [
            'project_id' => env('FIREBASE_PROJECT_ID', 'sos-urgently-ac307'),
            'credentials' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-credentials.json')),
        ],
    ],
];
