<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present_on_api_responses(): void
    {
        $response = $this->get('/api/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('max-age=', $response->headers->get('Strict-Transport-Security'));
    }

    public function test_csp_header_is_set_on_html_pages(): void
    {
        $response = $this->get('/sos-call');
        $response->assertStatus(200);
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy', ''));
    }

    public function test_csp_header_not_set_on_api_json_responses(): void
    {
        $response = $this->get('/api/health');
        // API JSON doesn't need CSP (no scripts executed)
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertEmpty($csp);
    }

    public function test_permissions_policy_disables_sensitive_apis(): void
    {
        $response = $this->get('/api/health');
        $policy = $response->headers->get('Permissions-Policy', '');
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('camera=()', $policy);
    }
}
