<?php

namespace App\Console\Commands;

use App\Jobs\SendSosCallActivationEmail;
use App\Models\Agreement;
use App\Models\Subscriber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Generate SOS-Call codes retroactively for existing subscribers of a partner.
 *
 * Use case: a partner existed before the SOS-Call system, and the admin
 * activates sos_call_active=true on their agreement. All existing subscribers
 * then need codes. This command handles the bulk generation idempotently.
 *
 * Usage:
 *   php artisan sos-call:generate-codes-for-partner <partner_firebase_id>
 *   php artisan sos-call:generate-codes-for-partner <id> --send-emails
 *   php artisan sos-call:generate-codes-for-partner <id> --dry-run
 */
class GenerateSosCallCodesBulk extends Command
{
    protected $signature = 'sos-call:generate-codes-for-partner
                            {partner_firebase_id : The partner to process}
                            {--send-emails : Dispatch activation emails after generating codes}
                            {--dry-run : Show what would be done without doing it}';

    protected $description = 'Bulk generate SOS-Call codes for existing subscribers of a partner';

    public function handle(): int
    {
        $partnerId = $this->argument('partner_firebase_id');
        $dryRun = (bool) $this->option('dry-run');
        $sendEmails = (bool) $this->option('send-emails');

        $agreement = Agreement::where('partner_firebase_id', $partnerId)->first();
        if (!$agreement) {
            $this->error("Agreement not found for partner_firebase_id: {$partnerId}");
            return self::FAILURE;
        }

        if (!$agreement->sos_call_active) {
            $this->error("Agreement has sos_call_active=false. Activate it first before generating codes.");
            return self::FAILURE;
        }

        $this->info("Processing partner: {$agreement->partner_name}");
        $this->info("Agreement ID: {$agreement->id}");

        // Find subscribers without codes
        $subscribersQuery = Subscriber::where('agreement_id', $agreement->id)
            ->whereNull('sos_call_code')
            ->where('status', 'active');

        $count = $subscribersQuery->count();
        $this->info("Subscribers without code: {$count}");

        if ($count === 0) {
            $this->info("Nothing to do.");
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] Would generate {$count} codes");
            if ($sendEmails) {
                $this->warn("[DRY RUN] Would dispatch {$count} activation emails");
            }
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $generated = 0;
        $errors = 0;

        $subscribersQuery->chunk(100, function ($subscribers) use ($agreement, $sendEmails, &$generated, &$errors, $bar) {
            foreach ($subscribers as $subscriber) {
                try {
                    $subscriber->sos_call_code = $this->generateUniqueCode($agreement->partner_name);
                    $subscriber->sos_call_activated_at = now();

                    $duration = $agreement->default_subscriber_duration_days;
                    if ($duration) {
                        $subscriber->sos_call_expires_at = now()->addDays($duration);
                    } elseif ($agreement->expires_at) {
                        $subscriber->sos_call_expires_at = $agreement->expires_at;
                    }

                    $subscriber->save();
                    $generated++;

                    if ($sendEmails) {
                        SendSosCallActivationEmail::dispatch($subscriber);
                    }
                } catch (\Throwable $e) {
                    Log::error('[GenerateSosCallCodesBulk] Failed for subscriber', [
                        'subscriber_id' => $subscriber->id,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Generated: {$generated}");
        if ($errors > 0) {
            $this->warn("Errors: {$errors}");
        }
        if ($sendEmails) {
            $this->info("Emails dispatched to queue.");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function generateUniqueCode(string $partnerName): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $partnerName), 0, 3));
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }
        $year = date('Y');
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I, O, 0, 1

        do {
            $random = '';
            for ($i = 0; $i < 5; $i++) {
                $random .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $code = "{$prefix}-{$year}-{$random}";
        } while (Subscriber::where('sos_call_code', $code)->exists());

        return $code;
    }
}
