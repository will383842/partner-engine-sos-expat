<?php

namespace App\Mail;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the partner billing_email when admin clicks
 * "Valider tous & envoyer pour signature" in Filament.
 *
 * Tells the partner: 3 documents are ready, log into the dashboard to sign.
 */
class PartnerLegalDocumentsReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Agreement  $agreement
     * @param  array<\App\Models\PartnerLegalDocument>  $documents
     */
    public function __construct(public Agreement $agreement, public array $documents)
    {
    }

    public function envelope(): Envelope
    {
        $lang = $this->agreement->partner_legal_language ?? 'fr';
        $subject = match ($lang) {
            'en' => 'Action required — Sign your B2B partnership documents',
            default => 'Action requise — Signature de vos documents partenariat B2B',
        };
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner_legal.ready',
            with: [
                'agreement' => $this->agreement,
                'documents' => $this->documents,
                'partnerName' => $this->agreement->partner_name,
                'dashboardUrl' => rtrim(config('services.frontend_url', 'https://www.sos-expat.com'), '/')
                    . '/partner/documents-legaux',
                'language' => $this->agreement->partner_legal_language ?? 'fr',
            ],
        );
    }
}
