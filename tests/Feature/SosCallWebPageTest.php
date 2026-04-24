<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the /sos-call public Blade page.
 * Verifies that the landing page renders correctly for all supported locales
 * and contains the expected markup, Alpine.js component, and security config.
 */
class SosCallWebPageTest extends TestCase
{
    public function test_sos_call_page_renders_successfully(): void
    {
        $response = $this->get('/sos-call');

        $response->assertStatus(200);
        $response->assertViewIs('sos-call.index');
    }

    public function test_root_path_also_serves_sos_call(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('sos-call.index');
    }

    public function test_page_contains_essential_markup(): void
    {
        $response = $this->get('/sos-call');

        $html = $response->getContent();

        // Essential structural elements
        $this->assertStringContainsString('<title>SOS-Call', $html);
        $this->assertStringContainsString('SOS_CALL_CONFIG', $html, 'Client-side config must be embedded');
        $this->assertStringContainsString('sosCallApp()', $html, 'Alpine.js component must be initialized');
        $this->assertStringContainsString('x-show="state === \'initial\'"', $html, 'State machine must be present');
        $this->assertStringContainsString('checkSosCallCode', $html, 'Firebase callable name must be in the script');

        // State descriptions expected in the machine
        $this->assertStringContainsString("'access_granted'", $html);
        $this->assertStringContainsString("'phone_match_email_mismatch'", $html);
        $this->assertStringContainsString("'not_found'", $html);
        $this->assertStringContainsString("'rate_limited'", $html);
        $this->assertStringContainsString("'call_in_progress'", $html);

        // Call type buttons — now go through selectCallType → pick_phone → triggerCall
        $this->assertStringContainsString("selectCallType('expat')", $html);
        $this->assertStringContainsString("selectCallType('lawyer')", $html);
        $this->assertStringContainsString("triggerSosCallFromWeb", $html);
    }

    public function test_page_sets_noindex_meta_for_security(): void
    {
        $response = $this->get('/sos-call');

        $html = $response->getContent();
        $this->assertStringContainsString('name="robots"', $html);
        $this->assertStringContainsString('noindex', $html);
    }

    public function test_page_uses_fallback_locale_when_lang_query_is_invalid(): void
    {
        // Explicitly send no Accept-Language to test pure fallback
        $response = $this->withHeaders(['Accept-Language' => ''])
            ->get('/sos-call?lang=xx-invalid');

        $response->assertStatus(200);
        // Should fall back to French (default)
        $this->assertStringContainsString('lang="fr"', $response->getContent());
    }

    public function test_page_respects_explicit_locale_query(): void
    {
        $response = $this->get('/sos-call?lang=en');

        $response->assertStatus(200);
        $this->assertStringContainsString('lang="en"', $response->getContent());
    }

    public function test_page_sets_rtl_direction_for_arabic(): void
    {
        $response = $this->get('/sos-call?lang=ar');

        $response->assertStatus(200);
        $this->assertStringContainsString('dir="rtl"', $response->getContent());
    }

    public function test_page_sets_ltr_direction_for_non_rtl_locales(): void
    {
        foreach (['fr', 'en', 'es', 'de', 'pt', 'zh', 'ru', 'hi'] as $locale) {
            $response = $this->get('/sos-call?lang=' . $locale);
            $response->assertStatus(200);
            $this->assertStringContainsString('dir="ltr"', $response->getContent(), "Locale {$locale} should be LTR");
        }
    }

    public function test_page_respects_accept_language_header(): void
    {
        $response = $this->get('/sos-call', ['Accept-Language' => 'es-ES,es;q=0.9']);

        $response->assertStatus(200);
        $this->assertStringContainsString('lang="es"', $response->getContent());
    }

    public function test_countdown_is_240_seconds_matching_call_delay(): void
    {
        $response = $this->get('/sos-call');

        $html = $response->getContent();
        // The countdown default must match Firebase CALL_DELAY_SECONDS
        $this->assertStringContainsString('countdown: 240', $html);
    }

    public function test_page_exposes_firebase_region(): void
    {
        $response = $this->get('/sos-call');

        $html = $response->getContent();
        // Firebase region must be us-central1 to match partnerConfig.region
        $this->assertStringContainsString('us-central1', $html);
    }

    public function test_page_links_to_standard_access_fallback(): void
    {
        $response = $this->get('/sos-call');

        $html = $response->getContent();
        // The "no code? pay standard" fallback link must be present
        $this->assertStringContainsString('/sos-appel', $html);
    }
}
