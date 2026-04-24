<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriberMagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public Subscriber $subscriber;
    public string $authUrl;

    public function __construct(Subscriber $subscriber, string $authUrl)
    {
        $this->subscriber = $subscriber;
        $this->authUrl = $authUrl;
    }

    public function build()
    {
        $language = $this->subscriber->language ?? 'fr';
        $partnerName = $this->subscriber->agreement?->partner_name ?? 'SOS-Expat';
        $firstName = $this->subscriber->first_name ?: '';

        $template = EmailTemplate::resolve(
            EmailTemplate::TYPE_SUBSCRIBER_MAGIC_LINK,
            $language,
            $this->subscriber->partner_firebase_id
        );

        $subject = $template?->subject ?? 'Votre lien de connexion SOS-Expat';
        $bodyOverride = $template?->body_html;

        return $this
            ->to($this->subscriber->email)
            ->subject($subject)
            ->view('emails.subscriber_magic_link.fr', [
                'first_name' => $firstName,
                'partner_name' => $partnerName,
                'auth_url' => $this->authUrl,
                'subscriber' => $this->subscriber,
                'body_override' => $bodyOverride,
            ]);
    }
}
