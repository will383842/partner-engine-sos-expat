<?php

namespace App\Jobs;

use App\Mail\SubscriberInvitation;
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

        $frontendUrl = config('services.frontend_url', 'https://www.sos-expat.com');
        $invitationLink = "{$frontendUrl}/register/client?partnerInviteToken={$subscriber->invite_token}";

        $partnerName = $agreement?->partner_name ?? 'Partenaire SOS-Expat';
        $discountLabel = $agreement?->discount_label ?? 'une réduction exclusive';

        // Check for custom email template
        $customTemplate = EmailTemplate::where('partner_firebase_id', $subscriber->partner_firebase_id)
            ->where('type', 'invitation')
            ->where('is_active', true)
            ->first();

        try {
            if ($customTemplate) {
                // Use custom template: replace variables in body_html
                $body = str_replace(
                    ['{firstName}', '{partnerName}', '{discountLabel}', '{invitationLink}'],
                    [$subscriber->first_name ?? '', $partnerName, $discountLabel, $invitationLink],
                    $customTemplate->body_html,
                );

                $subject = str_replace(
                    ['{firstName}', '{partnerName}'],
                    [$subscriber->first_name ?? '', $partnerName],
                    $customTemplate->subject,
                );

                Mail::html($body, function ($message) use ($subscriber, $subject) {
                    $message->to($subscriber->email)->subject($subject);
                });
            } else {
                // Use default Blade template
                Mail::to($subscriber->email)->send(new SubscriberInvitation(
                    subscriber: $subscriber,
                    partnerName: $partnerName,
                    discountLabel: $discountLabel,
                    invitationLink: $invitationLink,
                ));
            }

            Log::info('Invitation email sent', [
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'custom_template' => $customTemplate !== null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
