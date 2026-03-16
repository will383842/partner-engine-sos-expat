<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckFailedJobs extends Command
{
    protected $signature = 'jobs:check-failed';
    protected $description = 'Check for failed jobs and alert if any exist';

    public function handle(): int
    {
        $count = DB::table('failed_jobs')->count();

        if ($count > 0) {
            Log::warning("Partner Engine: {$count} failed job(s) detected", [
                'count' => $count,
            ]);
            $this->warn("{$count} failed job(s) found — check logs");
        } else {
            $this->info('No failed jobs');
        }

        return self::SUCCESS;
    }
}
