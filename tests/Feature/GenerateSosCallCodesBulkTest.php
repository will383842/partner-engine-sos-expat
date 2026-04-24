<?php

namespace Tests\Feature;

use App\Jobs\SendSosCallActivationEmail;
use App\Models\Agreement;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Tests for the sos-call:generate-codes-for-partner command — critical for
 * migrating existing subscribers to the new SOS-Call system without duplication.
 */
class GenerateSosCallCodesBulkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    public function test_fails_when_partner_not_found(): void
    {
        $this->artisan('sos-call:generate-codes-for-partner', [
            'partner_firebase_id' => 'nonexistent',
        ])->assertFailed();
    }

    public function test_fails_when_sos_call_not_active(): void
    {
        Agreement::factory()->create([
            'partner_firebase_id' => 'partner_inactive',
            'sos_call_active' => false,
        ]);

        $this->artisan('sos-call:generate-codes-for-partner', [
            'partner_firebase_id' => 'partner_inactive',
        ])->assertFailed();
    }

    public function test_generates_codes_for_subscribers_without_codes(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_bulk',
            'partner_name' => 'BulkTest',
            'sos_call_active' => true,
        ]);

        Subscriber::factory()->count(5)->create([
            'partner_firebase_id' => 'partner_bulk',
            'agreement_id' => $agreement->id,
            'sos_call_code' => null,
            'status' => 'active',
        ]);

        $this->artisan('sos-call:generate-codes-for-partner', [
            'partner_firebase_id' => 'partner_bulk',
        ])->assertSuccessful();

        $this->assertEquals(
            5,
            Subscriber::where('agreement_id', $agreement->id)
                ->whereNotNull('sos_call_code')
                ->count()
        );
    }

    public function test_skips_subscribers_that_already_have_codes(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_mixed',
            'sos_call_active' => true,
        ]);

        // 3 with codes, 2 without
        Subscriber::factory()->count(3)->create([
            'partner_firebase_id' => 'partner_mixed',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'MIX-2026-' . substr(md5(uniqid()), 0, 5),
            'status' => 'active',
        ]);

        Subscriber::factory()->count(2)->create([
            'partner_firebase_id' => 'partner_mixed',
            'agreement_id' => $agreement->id,
            'sos_call_code' => null,
            'status' => 'active',
        ]);

        $this->artisan('sos-call:generate-codes-for-partner', [
            'partner_firebase_id' => 'partner_mixed',
        ])->assertSuccessful();

        // All 5 should now have codes
        $this->assertEquals(5, Subscriber::whereNotNull('sos_call_code')->count());
    }

    public function test_dry_run_does_not_write_to_db(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_dry',
            'sos_call_active' => true,
        ]);

        Subscriber::factory()->count(3)->create([
            'partner_firebase_id' => 'partner_dry',
            'agreement_id' => $agreement->id,
            'sos_call_code' => null,
            'status' => 'active',
        ]);

        $this->artisan('sos-call:generate-codes-for-partner', [
            'partner_firebase_id' => 'partner_dry',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertEquals(0, Subscriber::whereNotNull('sos_call_code')->count());
    }

    public function test_send_emails_flag_dispatches_jobs(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_emails',
            'sos_call_active' => true,
        ]);

        Subscriber::factory()->count(3)->create([
            'partner_firebase_id' => 'partner_emails',
            'agreement_id' => $agreement->id,
            'sos_call_code' => null,
            'status' => 'active',
        ]);

        $this->artisan('sos-call:generate-codes-for-partner', [
            'partner_firebase_id' => 'partner_emails',
            '--send-emails' => true,
        ])->assertSuccessful();

        Bus::assertDispatched(SendSosCallActivationEmail::class, 3);
    }

    public function test_generated_codes_are_unique(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_uniq',
            'partner_name' => 'UniqPartner',
            'sos_call_active' => true,
        ]);

        Subscriber::factory()->count(10)->create([
            'partner_firebase_id' => 'partner_uniq',
            'agreement_id' => $agreement->id,
            'sos_call_code' => null,
            'status' => 'active',
        ]);

        $this->artisan('sos-call:generate-codes-for-partner', [
            'partner_firebase_id' => 'partner_uniq',
        ])->assertSuccessful();

        $codes = Subscriber::whereNotNull('sos_call_code')->pluck('sos_call_code')->toArray();
        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes)); // All unique
    }
}
