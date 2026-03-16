<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExpireAgreementsCommand extends Command
{
    protected $signature = 'agreements:expire';
    protected $description = 'Expire overdue agreements and update subscriber statuses';

    public function handle(): int
    {
        // TODO Phase 6:
        // 1. Find agreements with status=active AND expires_at < now()
        // 2. Update status to expired
        // 3. Update all linked subscribers to status=expired
        // 4. Sync Firestore partner_subscribers docs
        // 5. Send notification to partner (email + Telegram)

        $this->info('agreements:expire — not implemented yet');
        return self::SUCCESS;
    }
}
