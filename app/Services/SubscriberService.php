<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Jobs\SendSubscriberInvitation;
use App\Jobs\SendSosCallActivationEmail;
use App\Jobs\SyncSubscriberToFirestore;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SubscriberService
{
    public function __construct(
        protected FirebaseService $firebase,
        protected AuditService $audit,
    ) {}

    /**
     * Create a single subscriber manually.
     */
    public function create(string $partnerFirebaseId, array $data, string $actorId, string $actorRole, ?string $ip = null): Subscriber
    {
        // Get active agreement for this partner (lock to prevent race condition on max_subscribers)
        $agreement = Agreement::where('partner_firebase_id', $partnerFirebaseId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->latest()
            ->first();

        // Check max_subscribers limit (protected by lockForUpdate above)
        if ($agreement && $agreement->max_subscribers) {
            $currentCount = Subscriber::where('agreement_id', $agreement->id)
                ->whereNull('deleted_at')
                ->count();

            if ($currentCount >= $agreement->max_subscribers) {
                throw new \Exception('Maximum subscriber limit reached for this agreement');
            }
        }

        $inviteToken = Str::random(64);

        $subscriberAttrs = [
            'partner_firebase_id' => $partnerFirebaseId,
            'agreement_id' => $agreement?->id,
            'email' => $data['email'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? null,
            'language' => $data['language'] ?? 'fr',
            'invite_token' => $inviteToken,
            'status' => 'invited',
            'invited_at' => now(),
            'tags' => $data['tags'] ?? [],
            'custom_fields' => $data['custom_fields'] ?? [],
            // Hierarchy fields (optional — partner-defined)
            'group_label' => !empty($data['group_label']) ? trim($data['group_label']) : null,
            'region' => !empty($data['region']) ? trim($data['region']) : null,
            'department' => !empty($data['department']) ? trim($data['department']) : null,
            'external_id' => !empty($data['external_id']) ? trim($data['external_id']) : null,
        ];

        // SOS-Call activation (system B) — only if agreement has sos_call_active=true
        $isSosCallActivation = $agreement && $agreement->sos_call_active;
        if ($isSosCallActivation) {
            $subscriberAttrs['sos_call_code'] = $this->generateUniqueSosCallCode(
                $agreement->partner_name ?? 'SOS'
            );
            $subscriberAttrs['sos_call_activated_at'] = now();
            $subscriberAttrs['status'] = 'active'; // SOS-Call subscribers are immediately active

            // Determine expiration: explicit > agreement default > agreement expires_at > null (permanent)
            if (!empty($data['expires_at'])) {
                $subscriberAttrs['sos_call_expires_at'] = $data['expires_at'];
            } elseif ($agreement->default_subscriber_duration_days) {
                $subscriberAttrs['sos_call_expires_at'] = now()->addDays(
                    $agreement->default_subscriber_duration_days
                );
            } elseif ($agreement->expires_at) {
                $subscriberAttrs['sos_call_expires_at'] = $agreement->expires_at;
            }

            // Enforce max_subscriber_duration_days cap if specified
            if (!empty($subscriberAttrs['sos_call_expires_at']) && $agreement->max_subscriber_duration_days) {
                $maxExpiresAt = now()->addDays($agreement->max_subscriber_duration_days);
                if ($subscriberAttrs['sos_call_expires_at'] > $maxExpiresAt) {
                    $subscriberAttrs['sos_call_expires_at'] = $maxExpiresAt;
                }
            }
        }

        $subscriber = Subscriber::create($subscriberAttrs);

        // Log activity
        $activityAttrs = [
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $partnerFirebaseId,
            'type' => $isSosCallActivation ? 'sos_call_activation' : 'invitation_sent',
            'created_at' => now(),
        ];
        if ($isSosCallActivation) {
            $activityAttrs['metadata'] = ['sos_call_code' => $subscriber->sos_call_code];
        }
        SubscriberActivity::create($activityAttrs);

        $this->audit->log($actorId, $actorRole, 'subscriber.created', 'subscriber', $subscriber->id, [
            'email' => $subscriber->email,
            'partner_firebase_id' => $partnerFirebaseId,
            'sos_call' => $isSosCallActivation,
            'sos_call_code' => $subscriber->sos_call_code,
        ], $ip);

        // Dispatch jobs: sync Firestore + send appropriate email
        SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');

        if ($isSosCallActivation) {
            SendSosCallActivationEmail::dispatch($subscriber);
        } else {
            SendSubscriberInvitation::dispatch($subscriber);
        }

        return $subscriber;
    }

    /**
     * Generate a unique SOS-Call code with format PREFIX-YEAR-RANDOM5.
     *
     * Example: XXX-2026-A3K9M
     *
     * Characters excluded: I, O, 0, 1 (visual confusion).
     * Loops until a unique code is found (database-level uniqueness guaranteed by index).
     */
    protected function generateUniqueSosCallCode(string $partnerName): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $partnerName), 0, 3));
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }
        $year = date('Y');
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $charsLength = strlen($chars);

        // Limit retries to avoid infinite loop (defensive — prefix+year pool is 32^5 = ~33M options)
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $random = '';
            for ($i = 0; $i < 5; $i++) {
                $random .= $chars[random_int(0, $charsLength - 1)];
            }
            $code = "{$prefix}-{$year}-{$random}";

            if (!Subscriber::where('sos_call_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException("Failed to generate unique SOS-Call code for prefix={$prefix} after 10 attempts");
    }

    /**
     * Update a subscriber.
     */
    public function update(Subscriber $subscriber, array $data, string $actorId, string $actorRole, ?string $ip = null): Subscriber
    {
        $before = $subscriber->toArray();
        $subscriber->update($data);

        $this->audit->log($actorId, $actorRole, 'subscriber.updated', 'subscriber', $subscriber->id, [
            'before' => array_intersect_key($before, $data),
            'after' => array_intersect_key($subscriber->toArray(), $data),
        ], $ip);

        return $subscriber->fresh();
    }

    /**
     * Soft-delete a subscriber.
     */
    public function delete(Subscriber $subscriber, string $actorId, string $actorRole, ?string $ip = null): void
    {
        $this->audit->log($actorId, $actorRole, 'subscriber.deleted', 'subscriber', $subscriber->id, [
            'email' => $subscriber->email,
        ], $ip);

        $subscriber->delete();

        // Delete Firestore doc
        SyncSubscriberToFirestore::dispatch($subscriber, 'delete');
    }

    /**
     * Suspend a subscriber.
     */
    public function suspend(Subscriber $subscriber, string $actorId, string $actorRole, ?string $ip = null): Subscriber
    {
        $subscriber->update(['status' => 'suspended']);

        $this->audit->log($actorId, $actorRole, 'subscriber.suspended', 'subscriber', $subscriber->id, [
            'email' => $subscriber->email,
        ], $ip);

        SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');

        return $subscriber->fresh();
    }

    /**
     * Reactivate a suspended subscriber.
     */
    public function reactivate(Subscriber $subscriber, string $actorId, string $actorRole, ?string $ip = null): Subscriber
    {
        $subscriber->update(['status' => 'active']);

        $this->audit->log($actorId, $actorRole, 'subscriber.reactivated', 'subscriber', $subscriber->id, [
            'email' => $subscriber->email,
        ], $ip);

        SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');

        return $subscriber->fresh();
    }

    /**
     * Resend invitation email.
     */
    public function resendInvitation(Subscriber $subscriber, string $actorId, string $actorRole, ?string $ip = null): void
    {
        if ($subscriber->status !== 'invited') {
            throw new \Exception('Can only resend invitation to subscribers with status "invited"');
        }

        $subscriber->update(['invited_at' => now()]);

        SubscriberActivity::create([
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $subscriber->partner_firebase_id,
            'type' => 'invitation_sent',
            'metadata' => ['resend' => true],
            'created_at' => now(),
        ]);

        SendSubscriberInvitation::dispatch($subscriber);

        $this->audit->log($actorId, $actorRole, 'subscriber.invitation_resent', 'subscriber', $subscriber->id, [
            'email' => $subscriber->email,
        ], $ip);
    }
}
