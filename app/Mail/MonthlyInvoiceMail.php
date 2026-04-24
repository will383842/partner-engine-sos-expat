<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\PartnerInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Monthly invoice email sent to partner's billing_email when GenerateMonthlyInvoices
 * creates a new invoice.
 *
 * Attaches the PDF invoice and provides the Stripe hosted payment link.
 */
class MonthlyInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PartnerInvoice $invoice)
    {
    }

    public function envelope(): Envelope
    {
        $partnerLang = $this->invoice->agreement->language ?? 'fr';
        $subject = match ($partnerLang) {
            'en' => "SOS-Call Invoice {$this->invoice->period} — {$this->invoice->total_amount} {$this->invoice->billing_currency}",
            'es' => "Factura SOS-Call {$this->invoice->period} — {$this->invoice->total_amount} {$this->invoice->billing_currency}",
            default => "Facture SOS-Call {$this->invoice->period} — {$this->invoice->total_amount} {$this->invoice->billing_currency}",
        };
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $partnerLang = $this->invoice->agreement->language ?? 'fr';

        $template = EmailTemplate::resolve(
            EmailTemplate::TYPE_MONTHLY_INVOICE,
            $partnerLang,
            $this->invoice->partner_firebase_id
        );

        if ($template) {
            return new Content(htmlString: $this->renderTemplate($template->body_html));
        }

        return new Content(
            view: "emails.monthly_invoice.{$this->resolvedLang($partnerLang)}",
            with: $this->viewVariables(),
        );
    }

    public function attachments(): array
    {
        $attachments = [];
        if ($this->invoice->pdf_path && Storage::disk('local')->exists($this->invoice->pdf_path)) {
            $filename = "facture-sos-call-{$this->invoice->invoice_number}.pdf";
            $attachments[] = Attachment::fromStorageDisk('local', $this->invoice->pdf_path)
                ->as($filename)
                ->withMime('application/pdf');
        }
        return $attachments;
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
            'period_label' => $this->formatPeriod($this->invoice->period),
            'active_subscribers' => $this->invoice->active_subscribers,
            'billing_rate' => number_format($this->invoice->billing_rate, 2, ',', ' '),
            'total_amount' => number_format($this->invoice->total_amount, 2, ',', ' '),
            'currency' => $this->invoice->billing_currency,
            'due_date' => $this->invoice->due_date->format('d/m/Y'),
            'stripe_hosted_url' => $this->invoice->stripe_hosted_url ?: '',
            'partner_name' => $agreement->partner_name ?? 'Partenaire',
            'company_name' => $agreement->company_name ?? '',
            'vat_number' => $agreement->vat_number ?? '',
            'calls_expert' => $this->invoice->calls_expert,
            'calls_lawyer' => $this->invoice->calls_lawyer,
        ];
    }

    protected function formatPeriod(string $period): string
    {
        // "2026-04" → "Avril 2026"
        [$year, $month] = explode('-', $period);
        $months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                   'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        return ($months[(int)$month - 1] ?? $period) . ' ' . $year;
    }

    protected function resolvedLang(string $lang): string
    {
        return in_array($lang, ['fr', 'en', 'es', 'de', 'pt', 'ar', 'zh', 'ru', 'hi'], true) ? $lang : 'fr';
    }
}
