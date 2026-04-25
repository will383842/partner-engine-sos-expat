<?php

use Illuminate\Support\Facades\Schedule;

// Cron: Expire overdue agreements (daily at 3:00 UTC)
Schedule::command('agreements:expire')->dailyAt('03:00')->timezone('UTC');

// Cron: Aggregate monthly stats (daily at 2:00 UTC)
Schedule::command('stats:aggregate')->dailyAt('02:00')->timezone('UTC');

// Cron: Check failed jobs (daily at 6:00 UTC)
Schedule::command('jobs:check-failed')->dailyAt('06:00')->timezone('UTC');

// Cron: Generate monthly SOS-Call invoices (1st of month at 06:00 UTC)
Schedule::command('invoices:generate-monthly')
    ->monthlyOn(1, '06:00')
    ->timezone('UTC')
    ->onOneServer()
    ->runInBackground();

// P0-2 monitoring 2026-04-25: alert on overdue partner invoices so finance can chase
// before the provider 30-day reserve auto-releases (B2B credit risk visibility).
Schedule::command('partner-invoices:alert-overdue')
    ->dailyAt('09:00')
    ->timezone('UTC')
    ->onOneServer();
