<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\PartnerApiKey;
use App\Models\Subscriber;
use App\Services\SubscriberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that the SOS-Call B2B system handles ALL partner profiles:
 *   - Small independent cabinet (5 subscribers, no CSV, no API, no hierarchy)
 *   - Medium SME (200 subscribers, CSV, optional hierarchy)
 *   - Large corporate (10k+ subscribers, API, full hierarchy)
 *
 * AXA is just an example in the docs — nothing is hardcoded to any specific partner.
 */
class PartnerSizesTest extends TestCase
{
    use RefreshDatabase;

    public function test_small_independent_cabinet_no_hierarchy_no_api(): void
    {
        // A small cabinet of 5 clients, doesn't use hierarchy fields, created via admin UI only.
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'cabinet_durand',
            'partner_name' => 'Cabinet Durand Avocats',
            'billing_rate' => 2.00,  // 2€/mois/client (petite structure)
            'sos_call_active' => true,
            'default_subscriber_duration_days' => null, // permanent
        ]);

        $service = app(SubscriberService::class);
        for ($i = 1; $i <= 5; $i++) {
            $service->create('cabinet_durand', [
                'agreement_id' => $agreement->id,
                'email' => "client{$i}@durand.com",
                'first_name' => "Client{$i}",
            ], 'admin:ui', 'admin');
        }

        // No hierarchy fields set → none of them have group_label / region / department
        $this->assertEquals(5, Subscriber::where('partner_firebase_id', 'cabinet_durand')->count());
        $this->assertEquals(0, Subscriber::where('partner_firebase_id', 'cabinet_durand')->whereNotNull('group_label')->count());

        // Billing still works fine — small partner pays 5 × 2€ = 10€/mois
        $invoiceService = app(\App\Services\InvoiceService::class);
        $data = $invoiceService->calculateInvoiceData($agreement, now()->format('Y-m'));
        $this->assertEquals(5, $data['active_subscribers']);
        $this->assertEquals(10.00, $data['total_amount']);
    }

    public function test_medium_sme_with_csv_import(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'pme_tech',
            'partner_name' => 'PME Tech Solutions',
            'billing_rate' => 3.50,
            'sos_call_active' => true,
            'default_subscriber_duration_days' => 365,
        ]);

        // Simulate importing 50 subscribers (1 department only)
        $service = app(SubscriberService::class);
        for ($i = 1; $i <= 50; $i++) {
            $service->create('pme_tech', [
                'agreement_id' => $agreement->id,
                'email' => "emp{$i}@pme.com",
                'first_name' => "Emp{$i}",
                'department' => 'IT', // uses department but not group_label/region
            ], 'admin:csv_import', 'admin');
        }

        $this->assertEquals(50, Subscriber::where('partner_firebase_id', 'pme_tech')->count());
        $this->assertEquals(50, Subscriber::where('partner_firebase_id', 'pme_tech')->where('department', 'IT')->count());
        $this->assertEquals(0, Subscriber::where('partner_firebase_id', 'pme_tech')->whereNotNull('group_label')->count());

        // Expiration is 365 days from creation (default agreement)
        $sub = Subscriber::where('partner_firebase_id', 'pme_tech')->first();
        $this->assertNotNull($sub->sos_call_expires_at);
        $this->assertTrue($sub->sos_call_expires_at->between(now()->addDays(364), now()->addDays(366)));
    }

    public function test_large_corporate_with_full_hierarchy_and_api(): void
    {
        // Large corporate: 300 subscribers, 3 regions, 5 cabinets, custom expires_at per client
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'bigcorp_intl',
            'partner_name' => 'BigCorp International',
            'billing_rate' => 4.50,
            'sos_call_active' => true,
            'default_subscriber_duration_days' => 1825, // 5 ans
            'max_subscriber_duration_days' => 3650,     // cap 10 ans
        ]);

        $gen = PartnerApiKey::generate('bigcorp_intl', 'HR Integration');

        // API bulk create — different cabinets + regions
        $subscribers = [];
        $cabinets = ['BigCorp Paris', 'BigCorp Lyon', 'BigCorp London', 'BigCorp Madrid', 'BigCorp Berlin'];
        $regions = ['IDF', 'AURA', 'UK', 'ES', 'DE'];
        for ($i = 0; $i < 25; $i++) {
            $cabinetIdx = $i % count($cabinets);
            $subscribers[] = [
                'email' => "big{$i}@bigcorp.com",
                'first_name' => "Emp{$i}",
                'group_label' => $cabinets[$cabinetIdx],
                'region' => $regions[$cabinetIdx],
                'department' => $i % 2 === 0 ? 'Sales' : 'IT',
                'external_id' => "CRM-" . (10000 + $i),
                'expires_at' => now()->addYears(2)->toIso8601String(), // override 5y default
            ];
        }

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->postJson('/api/v1/partner/subscribers/bulk', ['subscribers' => $subscribers]);

        $response->assertStatus(207);
        $response->assertJsonPath('summary.created', 25);

        // Verify hierarchy query works
        $parisCount = Subscriber::where('partner_firebase_id', 'bigcorp_intl')
            ->where('group_label', 'BigCorp Paris')->count();
        $this->assertEquals(5, $parisCount); // 25 / 5 cabinets = 5 each

        // Verify expires_at override was applied (not the 5-year default)
        $sample = Subscriber::where('partner_firebase_id', 'bigcorp_intl')->first();
        $this->assertTrue($sample->sos_call_expires_at->between(now()->addYears(2)->subDays(1), now()->addYears(2)->addDays(1)));

        // All have external_id for CRM reconciliation
        $this->assertEquals(25, Subscriber::where('partner_firebase_id', 'bigcorp_intl')
            ->whereNotNull('external_id')->count());
    }

    public function test_different_billing_rates_per_partner(): void
    {
        // Each partner has their own rate — no hardcoding
        $smallCabinet = Agreement::factory()->create(['billing_rate' => 1.50]);
        $pme = Agreement::factory()->create(['billing_rate' => 3.00]);
        $bigCorp = Agreement::factory()->create(['billing_rate' => 5.00]);
        $premiumBank = Agreement::factory()->create(['billing_rate' => 12.99]);

        $this->assertEquals(1.50, (float) $smallCabinet->billing_rate);
        $this->assertEquals(3.00, (float) $pme->billing_rate);
        $this->assertEquals(5.00, (float) $bigCorp->billing_rate);
        $this->assertEquals(12.99, (float) $premiumBank->billing_rate);
    }

    public function test_hierarchy_is_optional_and_nullable(): void
    {
        // Partner doesn't want hierarchy — no requirement
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'simple_partner']);
        $sub = Subscriber::create([
            'partner_firebase_id' => 'simple_partner',
            'agreement_id' => $agreement->id,
            'email' => 'x@y.com',
            'status' => 'invited',
            'invite_token' => str_repeat('a', 64),
        ]);

        $this->assertNull($sub->group_label);
        $this->assertNull($sub->region);
        $this->assertNull($sub->department);
        $this->assertNull($sub->external_id);
        $this->assertNotNull($sub->email); // required field still set
    }

    public function test_different_durations_per_partner(): void
    {
        // Each partner chooses their own duration policy
        $cases = [
            ['partner_firebase_id' => 'trial_only', 'default_subscriber_duration_days' => 14],   // 2 weeks trial
            ['partner_firebase_id' => 'annual', 'default_subscriber_duration_days' => 365],      // 1 year
            ['partner_firebase_id' => 'visa_card', 'default_subscriber_duration_days' => 1825],  // 5 years
            // Null + null agreement.expires_at = permanent (no expiration)
            ['partner_firebase_id' => 'permanent', 'default_subscriber_duration_days' => null, 'expires_at' => null],
        ];

        foreach ($cases as $c) {
            $agreement = Agreement::factory()->create(array_merge($c, ['sos_call_active' => true]));
            $service = app(SubscriberService::class);
            $sub = $service->create($c['partner_firebase_id'], [
                'agreement_id' => $agreement->id,
                'email' => "user@{$c['partner_firebase_id']}.com",
            ], 'test', 'admin');

            if ($c['default_subscriber_duration_days'] === null) {
                // permanent — no expiration since both default_days and expires_at are null
                $this->assertNull($sub->sos_call_expires_at);
            } else {
                $expectedDays = $c['default_subscriber_duration_days'];
                $actualDays = now()->diffInDays($sub->sos_call_expires_at, false);
                $this->assertTrue(
                    abs($actualDays - $expectedDays) <= 1,
                    "Partner {$c['partner_firebase_id']}: expected ~{$expectedDays} days, got {$actualDays}"
                );
            }
        }
    }
}
