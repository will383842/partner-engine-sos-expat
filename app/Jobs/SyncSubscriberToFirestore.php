<?php

namespace App\Jobs;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSubscriberToFirestore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Subscriber $subscriber,
        public string $action = 'upsert', // upsert|delete
    ) {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        // TODO Phase 3: Write/delete partner_subscribers/{inviteToken} doc in Firestore
    }
}
