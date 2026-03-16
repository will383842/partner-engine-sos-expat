<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Jobs\SyncSubscriberToFirestore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgreementService
{
    public function __construct(
        protected FirebaseService $firebase,
        protected AuditService $audit,
    ) {}

    /**
     * Create a new agreement for a partner.
     */
    public function create(string $partnerFirebaseId, array $data, string $actorId, string $actorRole, ?string $ip = null): Agreement
    {
        $agreement = Agreement::create([
            'partner_firebase_id' => $partnerFirebaseId,
            'partner_name' => $data['partner_name'] ?? null,
            'name' => $data['name'],
            'status' => $data['status'] ?? 'draft',
            'discount_type' => $data['discount_type'] ?? 'none',
            'discount_value' => $data['discount_value'] ?? 0,
            'discount_max_cents' => $data['discount_max_cents'] ?? null,
            'discount_label' => $data['discount_label'] ?? null,
            'commission_per_call_lawyer' => $data['commission_per_call_lawyer'] ?? 500,
            'commission_per_call_expat' => $data['commission_per_call_expat'] ?? 300,
            'commission_type' => $data['commission_type'] ?? 'fixed',
            'commission_percent' => $data['commission_percent'] ?? null,
            'max_subscribers' => $data['max_subscribers'] ?? null,
            'max_calls_per_subscriber' => $data['max_calls_per_subscriber'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->log($actorId, $actorRole, 'agreement.created', 'agreement', $agreement->id, [
            'partner_firebase_id' => $partnerFirebaseId,
            'name' => $agreement->name,
            'status' => $agreement->status,
        ], $ip);

        return $agreement;
    }

    /**
     * Update an existing agreement.
     */
    public function update(Agreement $agreement, array $data, string $actorId, string $actorRole, ?string $ip = null): Agreement
    {
        $before = $agreement->toArray();

        $agreement->update($data);

        $this->audit->log($actorId, $actorRole, 'agreement.updated', 'agreement', $agreement->id, [
            'before' => array_intersect_key($before, $data),
            'after' => array_intersect_key($agreement->toArray(), $data),
        ], $ip);

        // If status changed, sync all linked subscribers to Firestore
        if (isset($data['status']) && $before['status'] !== $data['status']) {
            $this->syncSubscribersOnStatusChange($agreement);
        }

        return $agreement->fresh();
    }

    /**
     * Soft-delete an agreement.
     */
    public function delete(Agreement $agreement, string $actorId, string $actorRole, ?string $ip = null): void
    {
        $this->audit->log($actorId, $actorRole, 'agreement.deleted', 'agreement', $agreement->id, [
            'name' => $agreement->name,
            'partner_firebase_id' => $agreement->partner_firebase_id,
        ], $ip);

        $agreement->delete();
    }

    /**
     * Renew an agreement: copy it with new dates, expire the old one.
     */
    public function renew(Agreement $agreement, array $overrides, string $actorId, string $actorRole, ?string $ip = null): Agreement
    {
        return DB::transaction(function () use ($agreement, $overrides, $actorId, $actorRole, $ip) {
            // Expire old agreement
            $agreement->update(['status' => 'expired']);

            // Create new agreement as a copy
            $newData = $agreement->only([
                'partner_firebase_id', 'partner_name', 'name',
                'discount_type', 'discount_value', 'discount_max_cents', 'discount_label',
                'commission_per_call_lawyer', 'commission_per_call_expat',
                'commission_type', 'commission_percent',
                'max_subscribers', 'max_calls_per_subscriber',
            ]);

            $newData = array_merge($newData, $overrides);
            $newData['status'] = $overrides['status'] ?? 'draft';
            $newData['name'] = $overrides['name'] ?? $agreement->name . ' (renouvelé)';

            $newAgreement = Agreement::create($newData);

            // Migrate subscribers to new agreement
            Subscriber::where('agreement_id', $agreement->id)
                ->whereNull('deleted_at')
                ->update(['agreement_id' => $newAgreement->id]);

            $this->audit->log($actorId, $actorRole, 'agreement.renewed', 'agreement', $newAgreement->id, [
                'old_agreement_id' => $agreement->id,
                'new_agreement_id' => $newAgreement->id,
            ], $ip);

            return $newAgreement;
        });
    }

    /**
     * When agreement status changes (paused/expired/active), sync all subscribers to Firestore.
     */
    protected function syncSubscribersOnStatusChange(Agreement $agreement): void
    {
        $subscribers = Subscriber::where('agreement_id', $agreement->id)
            ->whereNull('deleted_at')
            ->get();

        foreach ($subscribers as $subscriber) {
            // If agreement expired, expire subscribers too
            if ($agreement->status === 'expired') {
                $subscriber->update(['status' => 'expired']);
            }

            SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');
        }

        Log::info('Synced subscribers on agreement status change', [
            'agreement_id' => $agreement->id,
            'new_status' => $agreement->status,
            'subscribers_count' => $subscribers->count(),
        ]);
    }
}
