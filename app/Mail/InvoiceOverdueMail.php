<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\PartnerInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Overdue invoice email sent when SuspendOnNonPayment triggers and suspends
 * all active subscribers for an agreement because an invoice remained unpaid
 * past its due_date.
 */
class InvoiceOverdueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PartnerInvoice $invoice,
        public int $suspendedCount,
    ) {}

    public function envelope(): Envelope
    {
        $partnerLang = $this->invoice->agreement->language ?? 'fr';
        $subject = match ($partnerLang) {
            'en' => "⚠️ Your SOS-Call access has been suspended — Unpaid invoice {$this->invoice->invoice_number}",
            default => "⚠️ Vos accès SOS-Call suspendus — Facture {$this->invoice->invoice_number} impayée",
        };
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $partnerLang = $this->invoice->agreement->language ?? 'fr';

        $template = EmailTemplate::resolve(
            EmailTemplate::TYPE_INVOICE_OVERDUE,
            $partnerLang,
            $this->invoice->partner_firebase_id
        );

        if ($template) {
            return new Content(htmlString: $this->renderTemplate($template->body_html));
        }

        return new Content(
            view: "emails.invoice_overdue.{$this->resolvedLang($partnerLang)}",
            with: $this->viewVariables(),
        );
    }

    protected function renderTemplate(string $template): string
    {
        $vars = $this->viewVariables();
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }

    protected function viewVariables(): array
    {
        $agreement = $this->invoice->agreement;
        return [
            'invoice_number' => $this->invoice->invoice_number,
            'period' => $this->invoice->period,
            'total_amount' => number_format($this->invoice->total_amount, 2, ',', ' '),
            'currency' => $this->invoice->billing_currency,
            'due_date' => $this->invoice->due_date->format('d/m/Y'),
            'days_overdue' => (int) now()->diffInDays($this->invoice->due_date),
            'suspended_count' => $this->suspendedCount,
            'stripe_hosted_url' => $this->invoice->stripe_hosted_url ?: '',
            'partner_name' => $agreement->partner_name ?? 'Partenaire',
        ];
    }

    protected function resolvedLang(string $lang): string
    {
        return in_array($lang, ['fr', 'en', 'es', 'de', 'pt', 'ar', 'zh', 'ru', 'hi'], true) ? $lang : 'fr';
    }
}
