<?php

namespace App\Console\Commands;

use App\Models\PartnerInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * P0-2 monitoring 2026-04-25.
 *
 * SOS-Expat absorbs the commercial credit risk on B2B SOS-Call: providers are
 * credited at call completion (TwilioCallManager) with a 30-day reserve, even
 * when the partner has not yet paid the monthly invoice. This command runs
 * daily and alerts on unpaid partner invoices so the finance team can chase
 * them before the 30-day reserve auto-releases.
 *
 * Severity tiers (chase order):
 *   - >= 7 days late : "ATTENTION"
 *   - >= 14 days late: "URGENT"
 *   - >= 30 days late: "CRITICAL — provider holds will be released soon"
 *
 * Output: Laravel log (Log::warning) + optional Telegram alert when both
 *   TELEGRAM_BOT_TOKEN and TELEGRAM_ALERT_CHAT_ID env vars are configured.
 */
class AlertOverduePartnerInvoices extends Command
{
    protected $signature = 'partner-invoices:alert-overdue {--dry-run : Print summary without sending alerts}';
    protected $description = 'Alert on overdue partner invoices to monitor B2B credit risk (P0-2)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $today = now();
        $overdue = PartnerInvoice::query()
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->orderBy('due_date', 'asc')
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('✅ No overdue partner invoices.');
            return self::SUCCESS;
        }

        $byPartner = [];
        foreach ($overdue as $inv) {
            $key = $inv->partner_firebase_id ?: ('agreement:' . $inv->agreement_id);
            $daysLate = (int) round($today->diffInDays($inv->due_date, true));
            $byPartner[$key] ??= [
                'partner_firebase_id' => $inv->partner_firebase_id,
                'count' => 0,
                'total_amount_cents' => 0,
                'currency' => null,
                'max_days_late' => 0,
                'invoice_numbers' => [],
            ];
            $byPartner[$key]['count']++;
            $byPartner[$key]['total_amount_cents'] += (int) round(((float) $inv->total_amount) * 100);
            $byPartner[$key]['currency'] ??= $inv->billing_currency ?: 'EUR';
            $byPartner[$key]['max_days_late'] = max($byPartner[$key]['max_days_late'], $daysLate);
            $byPartner[$key]['invoice_numbers'][] = $inv->invoice_number;
        }

        $criticalCount = 0; // ≥30j late — provider holds about to auto-release
        $urgentCount = 0;   // ≥14j late
        $attentionCount = 0; // ≥7j late
        foreach ($byPartner as $row) {
            if ($row['max_days_late'] >= 30) $criticalCount++;
            elseif ($row['max_days_late'] >= 14) $urgentCount++;
            elseif ($row['max_days_late'] >= 7) $attentionCount++;
        }

        $totalAmount = array_sum(array_column($byPartner, 'total_amount_cents')) / 100;
        $summary = sprintf(
            '⚠️ %d overdue partner invoice(s) across %d partner(s) — total %.2f (CRITICAL=%d, URGENT=%d, ATTENTION=%d)',
            $overdue->count(),
            count($byPartner),
            $totalAmount,
            $criticalCount,
            $urgentCount,
            $attentionCount,
        );
        $this->warn($summary);

        Log::warning('[AlertOverduePartnerInvoices] B2B credit risk', [
            'total_invoices' => $overdue->count(),
            'partner_count' => count($byPartner),
            'critical_count' => $criticalCount,
            'urgent_count' => $urgentCount,
            'attention_count' => $attentionCount,
            'partners' => $byPartner,
        ]);

        if ($dryRun) {
            $this->info('Dry-run — no Telegram alert sent.');
            return self::SUCCESS;
        }

        $this->maybeSendTelegram($summary, $byPartner);

        return self::SUCCESS;
    }

    private function maybeSendTelegram(string $summary, array $byPartner): void
    {
        $token = (string) env('TELEGRAM_BOT_TOKEN', '');
        $chatId = (string) env('TELEGRAM_ALERT_CHAT_ID', '');
        if ($token === '' || $chatId === '') {
            $this->warn('Telegram not configured (TELEGRAM_BOT_TOKEN + TELEGRAM_ALERT_CHAT_ID), alert logged only.');
            return;
        }

        $lines = [$summary, '', '<b>Top late partners:</b>'];
        $top = collect($byPartner)
            ->sortByDesc('max_days_late')
            ->take(10);
        foreach ($top as $row) {
            $lines[] = sprintf(
                '• %s — %d invoices, %.2f %s, %d days late',
                $row['partner_firebase_id'] ?: 'unknown',
                $row['count'],
                $row['total_amount_cents'] / 100,
                $row['currency'] ?? 'EUR',
                $row['max_days_late'],
            );
        }
        $message = implode("\n", $lines);

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);
            if (!$response->successful()) {
                Log::warning('[AlertOverduePartnerInvoices] Telegram send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[AlertOverduePartnerInvoices] Telegram send threw', ['error' => $e->getMessage()]);
        }
    }
}
