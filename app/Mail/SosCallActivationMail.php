<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Activation email sent to a subscriber when their SOS-Call access is created.
 *
 * Uses the EmailTemplate resolver with fallback chain:
 * 1. Partner-specific template for subscriber's language
 * 2. Partner-specific template in French
 * 3. Global default template for subscriber's language
 * 4. Global default template in French
 * 5. Hard-coded Blade view (resources/views/emails/sos_call_activation/{lang}.blade.php)
 */
class SosCallActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscriber $subscriber)
    {
    }

    public function envelope(): Envelope
    {
        $language = $this->subscriber->language ?? 'fr';

        $template = EmailTemplate::resolve(
            EmailTemplate::TYPE_SOS_CALL_ACTIVATION,
            $language,
            $this->subscriber->partner_firebase_id
        );

        $subject = $template
            ? $this->renderTemplate($template->subject)
            : $this->defaultSubject($language);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $language = $this->subscriber->language ?? 'fr';
        if (!in_array($language, EmailTemplate::SUPPORTED_LANGUAGES, true)) {
            $language = 'fr';
        }

        $template = EmailTemplate::resolve(
            EmailTemplate::TYPE_SOS_CALL_ACTIVATION,
            $language,
            $this->subscriber->partner_firebase_id
        );

        if ($template) {
            return new Content(
                htmlString: $this->renderTemplate($template->body_html)
            );
        }

        // Fallback to Blade view
        return new Content(
            view: "emails.sos_call_activation.{$language}",
            with: $this->viewVariables(),
        );
    }

    /**
     * Render a template string by substituting variables.
     */
    protected function renderTemplate(string $template): string
    {
        $vars = $this->viewVariables();
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }

    /**
     * Variables available in templates and views.
     */
    protected function viewVariables(): array
    {
        $agreement = $this->subscriber->agreement;
        $sosCallUrl = config('services.sos_call.public_url', 'https://sos-call.sos-expat.com');

        return [
            'first_name' => $this->subscriber->first_name ?? '',
            'last_name' => $this->subscriber->last_name ?? '',
            'full_name' => $this->subscriber->full_name,
            'email' => $this->subscriber->email,
            // Phone registered by the partner — surfaced in the email so the
            // subscriber can verify the number and use it as alternative login.
            'phone' => $this->subscriber->phone ?? '',
            'country' => $this->subscriber->country ?? '',
            'partner_name' => $agreement?->partner_name ?? 'votre partenaire',
            'agreement_label' => $agreement?->name ?? '',
            'sos_call_code' => $this->subscriber->sos_call_code,
            'call_types_allowed' => $agreement?->call_types_allowed ?? 'both',
            'expires_at' => $this->subscriber->sos_call_expires_at?->format('d/m/Y') ?? '',
            'sos_call_url' => $sosCallUrl,
            'dashboard_url' => $sosCallUrl . '/mon-acces',
            'language' => $this->subscriber->language ?? 'fr',
        ];
    }

    protected function defaultSubject(string $language): string
    {
        return match ($language) {
            'en' => 'Your SOS-Call access is activated',
            'es' => 'Su acceso SOS-Call está activado',
            'de' => 'Ihr SOS-Call-Zugang ist aktiviert',
            'pt' => 'Seu acesso SOS-Call está ativado',
            'ar' => 'تم تفعيل وصولك إلى SOS-Call',
            'zh' => '您的 SOS-Call 访问已激活',
            'ru' => 'Ваш доступ SOS-Call активирован',
            'hi' => 'आपका SOS-Call एक्सेस सक्रिय हो गया है',
            default => 'Votre accès SOS-Call est activé',
        };
    }
}
