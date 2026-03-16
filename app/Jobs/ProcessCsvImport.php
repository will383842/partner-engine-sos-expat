<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600; // 1 hour max for large CSVs

    public function __construct(
        public int $csvImportId,
        public string $partnerFirebaseId,
        public string $filePath,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // TODO Phase 3: Parse CSV, validate rows, create subscribers, dispatch invitation emails
    }
}
