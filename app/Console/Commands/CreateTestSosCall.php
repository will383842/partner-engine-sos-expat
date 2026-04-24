<?php

namespace App\Console\Commands;

use App\Models\Agreement;
use App\Models\Subscriber;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Create (or refresh) a test partner + agreement (sos_call_active=true) +
 * subscriber with a fresh SOS-Call code, for quick end-to-end testing
 * against production https://sos-call.sos-expat.com.
 *
 *   php artisan sos-call:test-code --email=you@example.com --phone=+33612345678
 *
 * Output: the generated code (e.g. TES-2026-A3K9M) + the URL to test.
 *
 * Safe to re-run: if the subscriber already exists, the code is regenerated.
 */
class CreateTestSosCall extends Command
{
    protected $signature = 'sos-call:test-code
        {--email= : Email of the subscriber (required)}
        {--phone= : Phone in E.164 format (optional but recommended for phone+email flow)}
        {--partner=test_partner_demo : Fake firebase UID for the partner}
        {--partner-name=Test Partner Demo : Partner display name}
        {--types=both : Call types allowed (both|expat_only|lawyer_only)}';

    protected $description = 'Create a temporary partner+subscriber for testing sos-call.sos-expat.com';

    public function handle(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $this->error('--email is required');
            return 1;
        }

        $partnerFirebaseId = $this->option('partner');
        $partnerName = $this->option('partner-name');
        $phone = $this->option('phone') ?: null;
        $types = $this->option('types');

        if (!in_array($types, ['both', 'expat_only', 'lawyer_only'], true)) {
            $this->error('--types must be one of: both, expat_only, lawyer_only');
            return 1;
        }

        $agreement = Agreement::firstOrCreate(
            ['partner_firebase_id' => $partnerFirebaseId],
            [
                'partner_name' => $partnerName,
                'partner_email' => 'billing+' . $partnerFirebaseId . '@test.local',
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => now()->addYear(),
                'sos_call_active' => true,
                'billing_rate' => 3.00,
                'billing_currency' => 'EUR',
                'payment_terms_days' => 15,
                'call_types_allowed' => $types,
                'default_subscriber_duration_days' => 365,
            ]
        );

        if (!$agreement->sos_call_active) {
            $agreement->update([
                'sos_call_active' => true,
                'call_types_allowed' => $types,
            ]);
        }

        $code = $this->generateUniqueSosCallCode($partnerName);

        $subscriber = Subscriber::where('partner_firebase_id', $partnerFirebaseId)
            ->where('email', $email)
            ->first();

        if ($subscriber) {
            $subscriber->update([
                'phone' => $phone,
                'status' => 'active',
                'sos_call_code' => $code,
                'sos_call_activated_at' => now(),
                'sos_call_expires_at' => now()->addYear(),
                'calls_expert' => 0,
                'calls_lawyer' => 0,
            ]);
            $this->line('<fg=blue>Existing subscriber refreshed with new code.</>');
        } else {
            $subscriber = Subscriber::create([
                'partner_firebase_id' => $partnerFirebaseId,
                'agreement_id' => $agreement->id,
                'email' => $email,
                'phone' => $phone,
                'country' => 'FR',
                'language' => 'fr',
                'status' => 'active',
                'invite_token' => Str::random(64),
                'invited_at' => now(),
                'sos_call_code' => $code,
                'sos_call_activated_at' => now(),
                'sos_call_expires_at' => now()->addYear(),
                'tags' => [],
                'custom_fields' => [],
            ]);
            $this->line('<fg=blue>New subscriber created.</>');
        }

        $this->newLine();
        $this->info('=== Test SOS-Call credentials ===');
        $this->line('Partner:    <fg=yellow>' . $agreement->partner_name . '</>');
        $this->line('Email:      <fg=yellow>' . $subscriber->email . '</>');
        if ($subscriber->phone) {
            $this->line('Phone:      <fg=yellow>' . $subscriber->phone . '</>');
        }
        $this->line('Code:       <fg=green;options=bold>' . $subscriber->sos_call_code . '</>');
        $this->line('Types:      <fg=yellow>' . $types . '</>');
        $this->line('Expires:    <fg=yellow>' . $subscriber->sos_call_expires_at?->format('Y-m-d') . '</>');
        $this->newLine();
        $this->line('Test URL:   <fg=cyan>https://sos-call.sos-expat.com/</>');
        $this->line('            Use the code above, or switch to "Telephone + Email" tab.');
        $this->newLine();

        return 0;
    }

    /**
     * Same logic as SubscriberService but duplicated here to keep the command
     * self-contained.
     */
    protected function generateUniqueSosCallCode(string $partnerName): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $partnerName), 0, 3));
        $prefix = str_pad($prefix ?: 'TES', 3, 'X');
        $year = date('Y');
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // excludes I, O, 0, 1

        do {
            $suffix = '';
            for ($i = 0; $i < 5; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = "{$prefix}-{$year}-{$suffix}";
        } while (Subscriber::where('sos_call_code', $code)->exists());

        return $code;
    }
}
