<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Jobs\SendSubscriberInvitation;
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
        // Get active agreement for this partner
        $agreement = Agreement::where('partner_firebase_id', $partnerFirebaseId)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Check max_subscribers limit
        if ($agreement && $agreement->max_subscribers) {
            $currentCount = Subscriber::where('agreement_id', $agreement->id)
                ->whereNull('deleted_at')
                ->count();

            if ($currentCount >= $agreement->max_subscribers) {
                throw new \Exception('Maximum subscriber limit reached for this agreement');
            }
        }

        $inviteToken = Str::random(64);

        $subscriber = Subscriber::create([
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
        ]);

        // Log activity
        SubscriberActivity::create([
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $partnerFirebaseId,
            'type' => 'invitation_sent',
            'created_at' => now(),
        ]);

        $this->audit->log($actorId, $actorRole, 'subscriber.created', 'subscriber', $subscriber->id, [
            'email' => $subscriber->email,
            'partner_firebase_id' => $partnerFirebaseId,
        ], $ip);

        // Dispatch jobs: sync Firestore + send invitation email
        SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');
        SendSubscriberInvitation::dispatch($subscriber);

        return $subscriber;
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
