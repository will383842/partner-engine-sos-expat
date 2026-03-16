<?php

namespace App\Jobs;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSubscriberInvitation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Subscriber $subscriber,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // TODO Phase 6: Send invitation email via Laravel Mail (SubscriberInvitation mailable)
    }
}
