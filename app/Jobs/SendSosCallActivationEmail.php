<?php

namespace App\Jobs;

use App\Mail\SosCallActivationMail;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the SOS-Call activation email to a newly created subscriber.
 *
 * Dispatched by SubscriberService::create() when sos_call_active=true on agreement.
 * Queue: 'high' priority (activation email is UX-critical).
 *
 * Retries 3 times with exponential backoff.
 */
class SendSosCallActivationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // seconds (60s, 120s, 180s)
    public int $timeout = 30;

    public function __construct(public Subscriber $subscriber)
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        // Guard: subscriber must have a SOS-Call code
        if (!$this->subscriber->sos_call_code) {
            Log::warning('SendSosCallActivationEmail: subscriber has no sos_call_code', [
                'subscriber_id' => $this->subscriber->id,
            ]);
            return;
        }

        // Guard: subscriber must have an email
        if (!$this->subscriber->email) {
            Log::warning('SendSosCallActivationEmail: subscriber has no email', [
                'subscriber_id' => $this->subscriber->id,
            ]);
            return;
        }

        try {
            Mail::to($this->subscriber->email)
                ->send(new SosCallActivationMail($this->subscriber));

            Log::info('SOS-Call activation email sent', [
                'subscriber_id' => $this->subscriber->id,
                'email' => $this->subscriber->email,
                'sos_call_code' => $this->subscriber->sos_call_code,
            ]);
        } catch (\Throwable $e) {
            Log::error('SOS-Call activation email failed', [
                'subscriber_id' => $this->subscriber->id,
                'email' => $this->subscriber->email,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('SendSosCallActivationEmail failed after all retries', [
            'subscriber_id' => $this->subscriber->id,
            'email' => $this->subscriber->email,
            'exception' => $exception->getMessage(),
        ]);
    }
}
