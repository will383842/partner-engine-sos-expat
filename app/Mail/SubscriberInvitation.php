<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriberInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
        public string $partnerName,
        public string $discountLabel,
        public string $invitationLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->partnerName} vous offre un accès privilégié à SOS-Expat",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscriber-invitation',
        );
    }
}
