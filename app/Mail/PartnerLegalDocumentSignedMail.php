<?php

namespace App\Mail;

use App\Models\PartnerLegalAcceptance;
use App\Models\PartnerLegalDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Confirmation email sent to the signer after recording a click-wrap acceptance.
 *
 * Attaches the signed PDF (with embedded signature block) so the partner has
 * a hard copy. Body links to the dashboard for permanent re-download.
 */
class PartnerLegalDocumentSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PartnerLegalDocument $document,
        public PartnerLegalAcceptance $acceptance,
    ) {
    }

    public function envelope(): Envelope
    {
        $lang = $this->document->language ?? 'fr';
        $title = $this->document->title;
        $subject = match ($lang) {
            'en' => "Signed copy — {$title}",
            default => "Copie signée — {$title}",
        };
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner_legal.signed',
            with: [
                'document' => $this->document,
                'acceptance' => $this->acceptance,
                'partnerName' => $this->document->agreement->partner_name ?? '',
                'dashboardUrl' => rtrim(config('services.frontend_url', 'https://www.sos-expat.com'), '/')
                    . '/partner/documents-legaux',
                'language' => $this->document->language ?? 'fr',
            ],
        );
    }

    public function attachments(): array
    {
        $path = $this->acceptance->signed_pdf_path ?: $this->document->pdf_path;
        if (!$path || !Storage::disk('local')->exists($path)) {
            return [];
        }
        $filename = sprintf('%s-signed-v%s.pdf',
            $this->document->kind,
            $this->document->template_version ?: 'na',
        );
        return [
            Attachment::fromStorageDisk('local', $path)
                ->as($filename)
                ->withMime('application/pdf'),
        ];
    }
}
