<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AggregateMonthlyStats extends Command
{
    protected $signature = 'stats:aggregate';
    protected $description = 'Aggregate monthly partner statistics';

    public function handle(): int
    {
        // TODO Phase 6:
        // 1. For each active partner, compute monthly stats
        // 2. Upsert into partner_monthly_stats table

        $this->info('stats:aggregate — not implemented yet');
        return self::SUCCESS;
    }
}
