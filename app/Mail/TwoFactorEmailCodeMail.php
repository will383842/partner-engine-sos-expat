<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email containing the 6-digit 2FA code an admin must enter to complete login.
 * Sent by TwoFactorEmailController::sendCode after a successful password match.
 */
class TwoFactorEmailCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public int $minutesValid = 10,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SOS-Expat Admin — Code de connexion / Login code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.two-factor-email-code',
            with: [
                'name' => $this->user->name ?? $this->user->email,
                'code' => $this->code,
                'minutes' => $this->minutesValid,
            ],
        );
    }
}
