<?php

namespace App\Console\Commands;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Jobs\SyncSubscriberToFirestore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpireAgreementsCommand extends Command
{
    protected $signature = 'agreements:expire';
    protected $description = 'Expire overdue agreements and update subscriber statuses';

    public function handle(): int
    {
        // 1. Expire overdue agreements
        $expiredAgreements = Agreement::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('deleted_at')
            ->get();

        $this->info("Found {$expiredAgreements->count()} agreements to expire");

        foreach ($expiredAgreements as $agreement) {
            $agreement->update(['status' => 'expired']);

            $subscribers = Subscriber::where('agreement_id', $agreement->id)
                ->whereIn('status', ['invited', 'registered', 'active'])
                ->whereNull('deleted_at')
                ->get();

            foreach ($subscribers as $subscriber) {
                $subscriber->update(['status' => 'expired']);
                SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');
            }

            $this->notifyTelegram('partner-agreement-expiring', [
                'partner_id' => $agreement->partner_firebase_id,
                'agreement_name' => $agreement->name,
                'subscribers_expired' => $subscribers->count(),
            ]);

            Log::info('Agreement expired', [
                'agreement_id' => $agreement->id,
                'partner_id' => $agreement->partner_firebase_id,
                'subscribers_expired' => $subscribers->count(),
            ]);

            $this->line("  Expired: {$agreement->name} ({$subscribers->count()} subscribers)");
        }

        // 2. Warn for agreements expiring in 7 days or 1 day
        $expiringAgreements = Agreement::where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->whereNull('deleted_at')
            ->get();

        foreach ($expiringAgreements as $agreement) {
            $daysRemaining = (int) now()->diffInDays($agreement->expires_at);

            if ($daysRemaining === 7 || $daysRemaining <= 1) {
                $this->notifyTelegram('partner-agreement-expiring', [
                    'partner_id' => $agreement->partner_firebase_id,
                    'agreement_name' => $agreement->name,
                    'days_remaining' => $daysRemaining,
                ]);
                $this->line("  Warning: {$agreement->name} expires in {$daysRemaining} day(s)");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function notifyTelegram(string $eventSlug, array $data): void
    {
        $url = config('services.telegram_engine.url');
        $apiKey = config('services.telegram_engine.api_key');
        if (!$url || !$apiKey) return;

        try {
            Http::timeout(5)
                ->withHeaders(['X-Engine-Secret' => $apiKey])
                ->post("{$url}/api/events/{$eventSlug}", $data);
        } catch (\Exception $e) {
            Log::warning("Telegram notification failed: {$e->getMessage()}");
        }
    }
}
