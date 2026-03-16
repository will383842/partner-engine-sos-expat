<?php

namespace App\Mail;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgreementExpiring extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Agreement $agreement,
        public int $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Votre accord commercial SOS-Expat expire dans {$this->daysRemaining} jour(s)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.agreement-expiring',
        );
    }
}
