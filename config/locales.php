<?php

/**
 * Supported languages for the Filament panels (partner + admin).
 *
 * Each entry: iso code → [label in its own language, flag emoji, optional RTL flag]
 *
 * To add a language later:
 *   1. Add its entry here
 *   2. Create lang/<code>/ translation files
 *   3. That's it — the toggle + middleware + Filament pick it up automatically.
 *
 * The cookie "locale" is set by POST /locale/{code} and read by
 * SetLocaleFromCookie middleware on every request.
 */
return [

    // Default locale when the cookie is absent or invalid
    'default' => env('APP_LOCALE', 'fr'),

    // Currently activated languages (will grow to 9: fr, en, es, de, pt, ar, zh, ru, hi)
    'enabled' => [
        'fr' => ['label' => 'Français',  'flag' => '🇫🇷', 'rtl' => false],
        'en' => ['label' => 'English',   'flag' => '🇬🇧', 'rtl' => false],
        // 'es' => ['label' => 'Español',  'flag' => '🇪🇸', 'rtl' => false],
        // 'de' => ['label' => 'Deutsch',  'flag' => '🇩🇪', 'rtl' => false],
        // 'pt' => ['label' => 'Português', 'flag' => '🇵🇹', 'rtl' => false],
        // 'ar' => ['label' => 'العربية',  'flag' => '🇸🇦', 'rtl' => true],
        // 'zh' => ['label' => '中文',      'flag' => '🇨🇳', 'rtl' => false],
        // 'ru' => ['label' => 'Русский',  'flag' => '🇷🇺', 'rtl' => false],
        // 'hi' => ['label' => 'हिन्दी',    'flag' => '🇮🇳', 'rtl' => false],
    ],

];
