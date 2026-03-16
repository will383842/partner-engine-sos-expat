<?php

namespace App\Jobs;

use App\Mail\SubscriberInvitation;
use App\Models\Agreement;
use App\Models\EmailTemplate;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriberInvitation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Subscriber $subscriber,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $subscriber = $this->subscriber;
        $agreement = $subscriber->agreement;

        // Build invitation link
        $frontendUrl = config('services.frontend_url', 'https://www.sos-expat.com');
        $invitationLink = "{$frontendUrl}/inscription?partnerInviteToken={$subscriber->invite_token}";

        // Get partner name from agreement or Firestore
        $partnerName = $agreement?->partner_name ?? 'Partenaire SOS-Expat';
        $discountLabel = $agreement?->discount_label ?? 'une réduction exclusive';

        try {
            Mail::to($subscriber->email)->send(new SubscriberInvitation(
                subscriber: $subscriber,
                partnerName: $partnerName,
                discountLabel: $discountLabel,
                invitationLink: $invitationLink,
            ));

            Log::info('Invitation email sent', [
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Retry via queue
        }
    }
}
